<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Twilio\Rest\Client as TwilioClient;

class OtpPasswordResetController extends Controller{

    public function sendOtp(Request $request): JsonResponse {
        $request->validate([
            'identifier' => 'required|string',
        ]);

        $raw        = trim($request->identifier);
        $type       = $this->detectType($raw);
        $identifier = $this->normalizeIdentifier($raw, $type);

        // ── Vérifier que l'utilisateur existe ────────────────────────────────
        $user = $this->findUser($identifier, $type);

        if (!$user) {
            // Sécurité : ne pas révéler si le compte existe ou non
            return response()->json([
                'success' => true,
                'message' => $type === 'email'
                    ? 'Si un compte existe avec cet email, vous recevrez un code.'
                    : 'Si un compte existe avec ce numéro, vous recevrez un SMS.',
                'type'    => $type,
            ]);
        }

        if ($user == null) {
            // Sécurité : ne pas révéler si le compte existe ou non
            return response()->json([
                'success' => true,
                'message' => $type === 'email'
                    ? 'Si un compte existe avec cet email, vous recevrez un code.'
                    : 'Si un compte existe avec ce numéro, vous recevrez un SMS.',
                'type'    => $type,
            ]);
        }

        // ── Anti-spam : vérifier le délai entre deux envois ──────────────────
        $lastOtp = PasswordResetOtp::where('identifier', $identifier)
                                   ->where('identifier_type', $type)
                                   ->latest()
                                   ->first();



        if ($lastOtp) {
            $secondsSinceLast = (int) $lastOtp->created_at->diffInSeconds(now(), false);

            if ($secondsSinceLast < PasswordResetOtp::RESEND_DELAY && !$lastOtp->isExpired()) {
                $waitSeconds = PasswordResetOtp::RESEND_DELAY - $secondsSinceLast;
                return response()->json([
                    'success'      => false,
                    'message'      => "Veuillez attendre {$waitSeconds} secondes avant de demander un nouveau code.",
                    'code'         => 'RESEND_TOO_SOON',
                    'wait_seconds' => $waitSeconds,
                ], 429);
            }
        }


        // ── Générer l'OTP ────────────────────────────────────────────────────
        $otp = PasswordResetOtp::generateFor($identifier, $type);

        // ── Envoyer le code ──────────────────────────────────────────────────
        try {
            if ($type === 'email') {
                $this->sendByEmail($user, $otp->code);
            } else {
                $this->sendBySms($identifier, $otp->code, $user->name);
            }
        } 

        catch (\Exception $e) {
            Log::error('[OtpReset] Erreur envoi', [
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);

            // Supprimer l'OTP si l'envoi a échoué
            $otp->delete();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi du code. Veuillez réessayer.',
                'code'    => 'SEND_FAILED',
            ], 500);
        }

        Log::info('[OtpReset] Code envoyé', [
            'identifier' => substr($identifier, 0, 4) . '***',
            'type'       => $type,
            'expires_at' => $otp->expires_at->toIso8601String(),
        ]);

        return response()->json([
            'success'      => true,
            'message'      => $type === 'email'
                ? 'Un code à 6 chiffres a été envoyé à votre email.'
                : 'Un code à 6 chiffres a été envoyé par SMS.',
            'type'         => $type,
            'expires_in'   => PasswordResetOtp::TTL_MINUTES * 60,
            'resend_after' => PasswordResetOtp::RESEND_DELAY,
          
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse {
        $request->validate([
            'identifier' => 'required|string',
            'code'       => 'required|string|size:6|regex:/^[0-9]{6}$/',
        ]);

        $raw        = trim($request->identifier);
        $type       = $this->detectType($raw);
        $identifier = $this->normalizeIdentifier($raw, $type);

        $otp = PasswordResetOtp::forIdentifier($identifier, $type)
                               ->valid()
                               ->latest()
                               ->first();

        // ── Code introuvable ou expiré ────────────────────────────────────────
        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'Code expiré ou invalide. Demandez un nouveau code.',
                'code'    => 'OTP_EXPIRED',
            ], 422);
        }

        // ── Trop de tentatives ────────────────────────────────────────────────
        if ($otp->hasExceededAttempts()) {
            $otp->delete();
            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives. Demandez un nouveau code.',
                'code'    => 'MAX_ATTEMPTS',
            ], 422);
        }

        // ── Vérification du code ──────────────────────────────────────────────
        if (!$otp->verify($request->code)) {
            $remaining = PasswordResetOtp::MAX_ATTEMPTS - $otp->fresh()->attempts;
            return response()->json([
                'success'           => false,
                'message'           => "Code incorrect. {$remaining} tentative(s) restante(s).",
                'code'              => 'WRONG_CODE',
                'attempts_remaining'=> max(0, $remaining),
            ], 422);
        }

        // ── Code correct — générer un token de réinitialisation temporaire ────
        // Ce token remplace l'OTP pour autoriser uniquement le reset du mot de passe
        $resetToken = hash('sha256', $identifier . now()->timestamp . rand(1000, 9999));

        // Stocker le token en cache (ou en base) pendant 10 minutes
        cache()->put(
            "otp_reset_token:{$resetToken}",
            ['identifier' => $identifier, 'type' => $type],
            now()->addMinutes(10)
        );

        Log::info('[OtpReset] Code vérifié avec succès', [
            'identifier' => substr($identifier, 0, 4) . '***',
            'type'       => $type,
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Code vérifié avec succès.',
            'reset_token'  => $resetToken,
            'expires_in'   => 600, // 10 minutes pour saisir le nouveau mot de passe
        ]);
    }

    public function resetPassword(Request $request): JsonResponse{
        $request->validate([
            'reset_token'           => 'required|string',
            'password'              => ['required', 'confirmed', 'min:8', Rules\Password::defaults()],
            'password_confirmation' => 'required|string',
        ]);

        // ── Vérifier le reset_token ───────────────────────────────────────────
        $cacheKey = "otp_reset_token:{$request->reset_token}";
        $data     = cache()->get($cacheKey);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Session expirée. Recommencez la procédure.',
                'code'    => 'RESET_TOKEN_EXPIRED',
            ], 422);
        }

        $identifier = $data['identifier'];
        $type       = $data['type'];

        // ── Trouver l'utilisateur ─────────────────────────────────────────────
        $user = $this->findUser($identifier, $type);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        // ── Mettre à jour le mot de passe ─────────────────────────────────────
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // ── Révoquer toutes les sessions existantes (sécurité) ────────────────
        $user->tokens()->delete();

        // ── Supprimer le token de reset ───────────────────────────────────────
        cache()->forget($cacheKey);

        // ── Créer un nouveau token d'authentification ─────────────────────────
        $authToken = $user->createToken('reset_auth_token')->plainTextToken;

        $user->update(['last_seen_at' => now()]);

        Log::info('[OtpReset] Mot de passe réinitialisé', [
            'user_id' => $user->id,
            'type'    => $type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès.',
            'token'   => $authToken,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role'  => $user->role,
            ],
        ]);
    }


    public function resendOtp(Request $request): JsonResponse   {
        // Réutilise sendOtp — même logique avec anti-spam intégré
        return $this->sendOtp($request);
    }


    private function detectType(string $input): string {
        return filter_var($input, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    }

    private function findUser(string $identifier, string $type): ?User{
        if ($type === 'email') {
            return User::where('email', $identifier)->first();
        }

        if ($type === 'phone') {
            return User::where('phone', $identifier)->first();
        }

        return null;
    }


    private function normalizeIdentifier(string $input, string $type): string {
        if ($type === 'email') {
            return strtolower(trim($input));
        }

        // Nettoyer
        $phone = preg_replace('/\D/', '', $input);

        // Ajouter indicatif Bénin si absent
        if (!str_starts_with($phone, '229')) {
            $phone = '229' . $phone;
        }

        return '+' . $phone;
    }

    // ── Envoi Email ───────────────────────────────────────────────────────────

    private function sendByEmail(User $user, string $code): void {
        Mail::send([], [], function ($message) use ($user, $code) {
            $message
                ->to($user->email, $user->name)
                ->subject('Votre code de réinitialisation CarEasy')
                ->html($this->buildEmailHtml($user->name, $code));
        });
    }

    private function buildEmailHtml(string $name, string $code): string{
        $firstName = explode(' ', $name)[0];
        $minutes   = PasswordResetOtp::TTL_MINUTES;

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px;">
    <tr>
      <td align="center">
        <table width="480" cellpadding="0" cellspacing="0"
               style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#E63946,#FF6B6B);padding:32px;text-align:center;">
              <div style="font-size:36px;font-weight:900;color:#fff;letter-spacing:2px;">CarEasy</div>
              <div style="color:rgba(255,255,255,0.85);font-size:14px;margin-top:6px;">
                Réinitialisation de mot de passe
              </div>
            </td>
          </tr>
          <!-- Body -->
          <tr>
            <td style="padding:36px 40px;">
              <p style="font-size:16px;color:#2D3436;margin:0 0 12px;">
                Bonjour <strong>{$firstName}</strong>,
              </p>
              <p style="font-size:15px;color:#636e72;margin:0 0 28px;line-height:1.6;">
                Vous avez demandé à réinitialiser votre mot de passe. 
                Voici votre code de vérification :
              </p>
              <!-- Code OTP -->
              <div style="background:#FFF5F5;border:2px dashed #E63946;border-radius:12px;
                          padding:24px;text-align:center;margin:0 0 28px;">
                <div style="font-size:48px;font-weight:900;letter-spacing:12px;
                            color:#E63946;font-family:monospace;">{$code}</div>
                <div style="font-size:13px;color:#b2bec3;margin-top:8px;">
                  Valide pendant <strong style="color:#E63946;">{$minutes} minutes</strong>
                </div>
              </div>
              <p style="font-size:13px;color:#b2bec3;line-height:1.6;margin:0 0 8px;">
                Ne partagez jamais ce code. Si vous n'êtes pas à l'origine de cette demande, 
                ignorez cet email.
              </p>
            </td>
          </tr>
          <!-- Footer -->
          <tr>
            <td style="background:#f8f9fa;padding:20px 40px;text-align:center;
                        border-top:1px solid #f0f0f0;">
              <p style="font-size:12px;color:#b2bec3;margin:0;">
                © {$this->currentYear()} CarEasy · Tous droits réservés
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    private function currentYear(): string{
        return date('Y');
    }

    // ── Envoi SMS via Twilio ──────────────────────────────────────────────────

    private function sendBySms(string $phone, string $code, string $name): void {
        $firstName = explode(' ', $name)[0];
        $minutes   = PasswordResetOtp::TTL_MINUTES;

        $sid    = env('TWILIO_ACCOUNT_SID');
        $token  = env('TWILIO_AUTH_TOKEN');
        $msgSid = env('TWILIO_MESSAGING_SERVICE_SID');

        if (!$sid || !$token || !$msgSid) {
            throw new \RuntimeException('Twilio non configuré (variables manquantes).');
        }

        $twilio = new TwilioClient($sid, $token);

        $twilio->messages->create($phone, [
            'messagingServiceSid' => $msgSid,
            'body'                => "CarEasy - Bonjour {$firstName}, votre code de réinitialisation est : {$code}\nValide {$minutes} min. Ne le partagez jamais.",
        ]);
    }

}