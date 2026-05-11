<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class VerifyContactController extends Controller
{
    public function send(Request $request): JsonResponse{
        
        $identifier_raw = trim($request->input('identifier', ''));
        $type           = $request->input('type', '');

        if (empty($identifier_raw)) {
            return response()->json(['success' => false, 'message' => 'Le champ contact est requis.', 'code' => 'INVALID_FORMAT'], 422);
        }
        if (!in_array($type, ['email', 'phone'])) {
            return response()->json(['success' => false, 'message' => 'Type invalide.', 'code' => 'INVALID_FORMAT'], 422);
        }

        // ── Validation du format ──────────────────────────────────────────────
        if ($type === 'email') {
            if (!filter_var($identifier_raw, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'adresse email est invalide.',
                    'code'    => 'INVALID_FORMAT',
                ], 422);
            }
            $identifier = strtolower(trim($identifier_raw));
        } 
        else {
            $digits = preg_replace('/\D/', '', $identifier_raw);
            if (strlen($digits) < 8 || strlen($digits) > 15) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le numéro de téléphone est invalide (8–15 chiffres).',
                    'code'    => 'INVALID_FORMAT',
                ], 422);
            }
            $identifier = $this->normalizePhoneIdentifier($identifier_raw);
            if (!$identifier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Numéro non reconnu. Formats acceptés : '
                            . 'XXXXXXXX · 01XXXXXXXX · +229XXXXXXXX  · +22901XXXXXXXX ',
                    'code'    => 'INVALID_FORMAT',
                    'expected_format' => '+22901XXXXXXXX',
                ], 422);
            }
        }

        $this->resetDbConnection();

        // ── Déjà utilisé ? ────────────────────────────────────────────────────
        try {
            if ($type === 'email') {
                $already = User::where('email', $identifier)->exists();
            } else {
                $already = User::where('phone', $identifier)->exists();
            }

            if ($already) {
                return response()->json([
                    'success' => false,
                    'message' => $type === 'email'
                        ? 'Cette adresse email est déjà associée à un compte.'
                        : 'Ce numéro de téléphone est déjà associé à un compte.',
                    'code'    => 'ALREADY_USED',
                ], 422);
            }
        } 
        catch (\Exception $e) {
            $this->resetDbConnection();
            Log::warning('[VerifyContact] Erreur vérif doublon', ['error' => $e->getMessage()]);
            // On continue — la vérification d'unicité se fera à l'inscription
        }

        // ── Anti-spam via cache fichier ───────────────────────────────────────
        $spamKey  = 'otp_spam_' . md5($identifier . $type);
        try {
            $lastSent = Cache::store('file')->get($spamKey);
            if ($lastSent) {
                $wait = PasswordResetOtp::RESEND_DELAY - (time() - (int)$lastSent);
                if ($wait > 0) {
                    return response()->json([
                        'success'      => false,
                        'message'      => "Veuillez attendre {$wait} secondes avant de renvoyer un code.",
                        'code'         => 'RESEND_TOO_SOON',
                        'wait_seconds' => $wait,
                    ], 429);
                }
            }
        } catch (\Exception $e) {
            // Cache indisponible — on ignore l'anti-spam
        }

        // ── Générer le code OTP ───────────────────────────────────────────────
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // ── Sauvegarder en DB avec gestion PostgreSQL ─────────────────────────
        try {
            $this->resetDbConnection();

            // Supprimer anciens OTP
            DB::statement('DELETE FROM password_reset_otps WHERE identifier = ? AND identifier_type = ?', [
                $identifier,
                $type,
            ]);

            // Insérer le nouveau OTP avec des paramètres liés (évite le bug de quoting)
            $expiresAt = now()->addMinutes(PasswordResetOtp::TTL_MINUTES)->toDateTimeString();
            $now       = now()->toDateTimeString();

            DB::statement(
                'INSERT INTO password_reset_otps (identifier, identifier_type, code, used, expires_at, attempts, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$identifier, $type, $code, false, $expiresAt, 0, $now, $now]
            );

        } catch (\Exception $e) {
            Log::error('[VerifyContact] Erreur sauvegarde OTP', [
                'error'      => $e->getMessage(),
                'identifier' => substr($identifier, 0, 5) . '***',
                'type'       => $type,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur lors de la génération du code. Réessayez.',
                'code'    => 'SERVER_ERROR',
            ], 500);
        }

        // ── Envoyer le code ───────────────────────────────────────────────────
        try {
            if ($type === 'email') {
                $this->sendEmail($identifier, $code);
            } else {
                $this->sendSms($identifier, $code);
            }
        } catch (\Exception $e) {
            // Supprimer l'OTP si l'envoi échoue
            try {
                $this->resetDbConnection();
                DB::statement('DELETE FROM password_reset_otps WHERE identifier = ? AND identifier_type = ?', [
                    $identifier, $type,
                ]);
            } catch (\Exception $_) {}

            Log::error('[VerifyContact] Envoi échoué', [
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $type === 'email'
                    ? 'Impossible d\'envoyer l\'email. Vérifiez que l\'adresse est correcte.'
                    : 'Impossible d\'envoyer le SMS. Vérifiez que le numéro est correct.',
                'code'    => 'SEND_FAILED',
            ], 500);
        }

        // ── Anti-spam : mémoriser l'envoi ─────────────────────────────────────
        try {
            Cache::store('file')->put($spamKey, time(), PasswordResetOtp::RESEND_DELAY + 10);
        } catch (\Exception $e) {}

        $masked = $type === 'email'
            ? $this->maskEmail($identifier)
            : $this->maskPhone($identifier);

        return response()->json([
            'success'      => true,
            'message'      => "Un code à 6 chiffres a été envoyé à {$masked}.",
            'masked'       => $masked,
            'expires_in'   => PasswordResetOtp::TTL_MINUTES * 60,
            'resend_after' => PasswordResetOtp::RESEND_DELAY,
        ]);
    }

    public function check(Request $request): JsonResponse{
        $identifier_raw = trim($request->input('identifier', ''));
        $type           = $request->input('type', '');
        $code_input     = trim($request->input('code', ''));

        // Validation manuelle
        if (empty($identifier_raw) || !in_array($type, ['email', 'phone'])) {
            return response()->json(['success' => false, 'message' => 'Paramètres invalides.', 'code' => 'INVALID_FORMAT'], 422);
        }
        if (!preg_match('/^[0-9]{6}$/', $code_input)) {
            return response()->json(['success' => false, 'message' => 'Le code doit contenir 6 chiffres.', 'code' => 'INVALID_FORMAT'], 422);
        }

        if ($type === 'email') {
            $identifier = strtolower(trim($identifier_raw));
        } 
        else {
            $identifier = $this->normalizePhoneIdentifier($identifier_raw);
            if (!$identifier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paramètres invalides.',
                    'code'    => 'INVALID_FORMAT',
                ], 422);
            }
        }

        // ── Réinitialiser la connexion ────────────────────────────────────────
        $this->resetDbConnection();

        // ── Récupérer l'OTP ───────────────────────────────────────────────────
        try {
            $rows = DB::select(
                'SELECT * FROM password_reset_otps WHERE identifier = ? AND identifier_type = ? AND used = ? AND expires_at > ? ORDER BY created_at DESC LIMIT 1',
                [$identifier, $type, false, now()->toDateTimeString()]
            );
            $otpRow = $rows[0] ?? null;
        } catch (\Exception $e) {
            $this->resetDbConnection();
            Log::error('[VerifyContact] Erreur lecture OTP', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur. Réessayez.', 'code' => 'SERVER_ERROR'], 500);
        }

        if (!$otpRow) {
            return response()->json([
                'success' => false,
                'message' => 'Code expiré ou invalide. Demandez un nouveau code.',
                'code'    => 'OTP_EXPIRED',
            ], 422);
        }

        if ((int)$otpRow->attempts >= PasswordResetOtp::MAX_ATTEMPTS) {
            try {
                $this->resetDbConnection();
                DB::statement('DELETE FROM password_reset_otps WHERE id = ?', [$otpRow->id]);
            } catch (\Exception $_) {}

            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives. Demandez un nouveau code.',
                'code'    => 'MAX_ATTEMPTS',
            ], 422);
        }

        // ── Incrémenter les tentatives ────────────────────────────────────────
        try {
            $this->resetDbConnection();
            DB::statement('UPDATE password_reset_otps SET attempts = attempts + 1, updated_at = ? WHERE id = ?', [
                now()->toDateTimeString(),
                $otpRow->id,
            ]);
        } catch (\Exception $e) {
            Log::warning('[VerifyContact] Erreur incrément tentative', ['error' => $e->getMessage()]);
        }

        // ── Vérifier le code ──────────────────────────────────────────────────
        if ($otpRow->code !== $code_input) {
            $newAttempts = (int)$otpRow->attempts + 1;
            $remaining   = max(0, PasswordResetOtp::MAX_ATTEMPTS - $newAttempts);
            return response()->json([
                'success'            => false,
                'message'            => "Code incorrect. {$remaining} tentative(s) restante(s).",
                'code'               => 'WRONG_CODE',
                'attempts_remaining' => $remaining,
            ], 422);
        }

        // ── Marquer comme utilisé ─────────────────────────────────────────────
        try {
            $this->resetDbConnection();
            DB::statement('UPDATE password_reset_otps SET used = ?, updated_at = ? WHERE id = ?', [
                true,
                now()->toDateTimeString(),
                $otpRow->id,
            ]);
        } catch (\Exception $e) {
            Log::warning('[VerifyContact] Erreur marquage used', ['error' => $e->getMessage()]);
        }

        // ── Générer le verify_token dans le cache fichier ─────────────────────
        $verifyToken = hash('sha256', $identifier . $type . now()->timestamp . random_int(1000, 9999));

        try {
            Cache::store('file')->put(
                "contact_verified:{$verifyToken}",
                ['identifier' => $identifier, 'type' => $type],
                900 // 15 minutes
            );
        } catch (\Exception $e) {
            Log::error('[VerifyContact] Erreur cache verify_token', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur. Réessayez.', 'code' => 'SERVER_ERROR'], 500);
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Contact vérifié avec succès.',
            'verify_token' => $verifyToken,
            'expires_in'   => 900,
        ]);
    }

  
    private function resetDbConnection(): void
    {
        try {
            // Forcer ROLLBACK de toute transaction en cours
            DB::statement('ROLLBACK');
        } catch (\Exception $_) {}

        try {
            // Reconnecter proprement
            DB::reconnect();
        } catch (\Exception $_) {}

        try {
            // S'assurer qu'on est hors transaction
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        } catch (\Exception $_) {}
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) return '***';
        [$local, $domain] = explode('@', $email, 2);
        $visible = substr($local, 0, min(3, strlen($local)));
        return $visible . str_repeat('*', max(1, strlen($local) - 3)) . '@' . $domain;
    }

    private function maskPhone(string $phone): string
    {
        $clean = preg_replace('/\D/', '', $phone);
        $len   = strlen($clean);
        if ($len <= 4) return str_repeat('*', $len);
        return substr($clean, 0, 2) . str_repeat('*', $len - 4) . substr($clean, -2);
    }

    private function sendEmail(string $email, string $code): void
    {
        $minutes = PasswordResetOtp::TTL_MINUTES;
        Mail::send([], [], function ($m) use ($email, $code, $minutes) {
            $m->to($email)
              ->subject("Votre code CarEasy : {$code}")
              ->html($this->emailHtml($code, $minutes));
        });
    }

    private function emailHtml(string $code, int $minutes): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">
          <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px;">
            <tr><td align="center">
              <table width="480" cellpadding="0" cellspacing="0"
                style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
                <tr>
                  <td style="background:linear-gradient(135deg,#E63946,#FF6B6B);padding:32px;text-align:center;">
                    <div style="font-size:32px;font-weight:900;color:#fff;letter-spacing:2px;">CarEasy</div>
                    <div style="color:rgba(255,255,255,.85);font-size:13px;margin-top:6px;">Vérification de votre inscription</div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:32px 36px;">
                    <p style="font-size:15px;color:#636e72;margin:0 0 24px;line-height:1.6;">
                      Pour finaliser votre inscription, entrez ce code :
                    </p>
                    <div style="background:#FFF5F5;border:2px dashed #E63946;border-radius:12px;
                                padding:24px;text-align:center;margin:0 0 24px;">
                      <div style="font-size:48px;font-weight:900;letter-spacing:14px;
                                  color:#E63946;font-family:monospace;">{$code}</div>
                      <div style="font-size:13px;color:#b2bec3;margin-top:8px;">
                        Valide pendant <strong style="color:#E63946;">{$minutes} minutes</strong>
                      </div>
                    </div>
                    <p style="font-size:12px;color:#b2bec3;line-height:1.5;margin:0;">
                      Ne partagez jamais ce code. Si vous n'avez pas demandé cette vérification, ignorez cet email.
                    </p>
                  </td>
                </tr>
                <tr>
                  <td style="background:#f8f9fa;padding:16px 36px;text-align:center;border-top:1px solid #f0f0f0;">
                    <p style="font-size:11px;color:#b2bec3;margin:0;">© CarEasy · Bénin</p>
                  </td>
                </tr>
              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
    }

    private function sendSms(string $phone, string $code): void
    {
        $sms  = app(\App\Services\SmsService::class);
        $sent = $sms->sendOtp($phone, $code, 'Inscription CarEasy');
        if (!$sent) {
            throw new \RuntimeException('SMS non envoyé par le gateway.');
        }
    }

    // Add the helper at the bottom of the class:
    private function normalizePhone(string $digits): ?string {
        $digits = ltrim($digits, '0');
        if (empty($digits)) return null;

        if (preg_match('/^\d{8}$/', $digits))    return '+229' . $digits;
        if (preg_match('/^229\d{8}$/', $digits)) return '+' . $digits;
        if (strlen($digits) >= 10)               return '+' . $digits;

        return null; // too short to be valid
    }

    private function normalizePhoneIdentifier(string $raw): ?string{
        $digits = preg_replace('/\D/', '', $raw);
        if (empty($digits)) return null;

        // Retirer le code pays Bénin s'il est présent
        if (str_starts_with($digits, '229')) {
            $digits = substr($digits, 3);
        }

        // Format local 10 chiffres commençant par 0
        if (preg_match('/^0\d{9}$/', $digits)) {
            return '+229' . $digits;
        }

        // Format local 8 chiffres → migration automatique
        if (preg_match('/^\d{8}$/', $digits)) {
            return '+229' . '01' . $digits;
        }

        return null;
    }

}