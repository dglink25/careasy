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

class VerifyContactController extends Controller
{
    // =========================================================================
    // POST /verify-contact/send
    // Body : identifier (email ou téléphone), type ('email'|'phone')
    // =========================================================================
    public function send(Request $request): JsonResponse
    {
        $identifier_raw = trim($request->input('identifier', ''));
        $type           = $request->input('type', '');

        // ── Validation des paramètres d'entrée ────────────────────────────────
        if (empty($identifier_raw)) {
            return response()->json([
                'success' => false,
                'message' => 'Le champ contact est requis.',
                'code'    => 'INVALID_FORMAT',
            ], 422);
        }

        if (! in_array($type, ['email', 'phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Type invalide. Valeurs acceptées : email, phone.',
                'code'    => 'INVALID_FORMAT',
            ], 422);
        }

        // ── Normalisation et validation du format ─────────────────────────────
        if ($type === 'email') {
            if (! filter_var($identifier_raw, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'adresse email est invalide.',
                    'code'    => 'INVALID_FORMAT',
                ], 422);
            }
            $identifier = strtolower(trim($identifier_raw));
        } else {
            $identifier = $this->normalizePhone($identifier_raw);
            if (! $identifier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Numéro invalide. Formats acceptés : XXXXXXXX · 01XXXXXXXX · +229XXXXXXXX',
                    'code'    => 'INVALID_FORMAT',
                ], 422);
            }
        }

        // ── Vérifier que l'identifiant n'est pas déjà utilisé ─────────────────
        $column  = $type === 'email' ? 'email' : 'phone';
        $already = User::where($column, $identifier)->exists();

        if ($already) {
            return response()->json([
                'success' => false,
                'message' => $type === 'email'
                    ? 'Cette adresse email est déjà associée à un compte.'
                    : 'Ce numéro de téléphone est déjà associé à un compte.',
                'code'    => 'ALREADY_USED',
            ], 422);
        }

        // ── Anti-spam : vérifier le délai entre deux envois en DB ─────────────
        $lastOtp = DB::selectOne(
            'SELECT created_at FROM password_reset_otps
             WHERE identifier = ? AND identifier_type = ?
             ORDER BY id DESC LIMIT 1',
            [$identifier, $type]
        );

        if ($lastOtp) {
            $secondsSinceLast = now()->diffInSeconds(\Carbon\Carbon::parse($lastOtp->created_at), false);
            // diffInSeconds avec false = négatif si dans le passé, positif si dans le futur
            // On veut : combien de secondes se sont écoulées depuis le dernier envoi
            $elapsed = now()->timestamp - \Carbon\Carbon::parse($lastOtp->created_at)->timestamp;
            $wait    = PasswordResetOtp::RESEND_DELAY - $elapsed;

            if ($wait > 0) {
                return response()->json([
                    'success'      => false,
                    'message'      => "Veuillez attendre {$wait} secondes avant de renvoyer un code.",
                    'code'         => 'RESEND_TOO_SOON',
                    'wait_seconds' => $wait,
                ], 429);
            }
        }

        // ── Générer le code OTP ───────────────────────────────────────────────
        $code      = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(PasswordResetOtp::TTL_MINUTES)->toDateTimeString();
        $now       = now()->toDateTimeString();

        // ── Supprimer les anciens OTP pour cet identifiant puis insérer ────────
        try {
            DB::statement(
                'DELETE FROM password_reset_otps WHERE identifier = ? AND identifier_type = ?',
                [$identifier, $type]
            );

            DB::statement(
                'INSERT INTO password_reset_otps
                    (identifier, identifier_type, code, used, expires_at, attempts, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$identifier, $type, $code, false, $expiresAt, 0, $now, $now]
            );
        } catch (\Exception $e) {
            Log::error('[VerifyContact] Erreur sauvegarde OTP', [
                'error'      => $e->getMessage(),
                'identifier' => $this->redact($identifier),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur lors de la génération du code. Réessayez.',
                'code'    => 'SERVER_ERROR',
            ], 500);
        }

        // ── Envoyer le code ───────────────────────────────────────────────────
        $sent = $this->dispatchCode($type, $identifier, $code);

        if (! $sent) {
            // Rollback : supprimer l'OTP si aucun canal n'a fonctionné
            try {
                DB::statement(
                    'DELETE FROM password_reset_otps WHERE identifier = ? AND identifier_type = ?',
                    [$identifier, $type]
                );
            } catch (\Exception $_) {}

            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'envoyer le code. Vérifiez que le contact est correct.',
                'code'    => 'SEND_FAILED',
            ], 500);
        }

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

    // =========================================================================
    // POST /verify-contact/check
    // Body : identifier, type, code
    // Retourne : verify_token (valide 60 min) à utiliser pour l'inscription
    // =========================================================================
    public function check(Request $request): JsonResponse
    {
        $identifier_raw = trim($request->input('identifier', ''));
        $type           = $request->input('type', '');
        $code_input     = trim($request->input('code', ''));

        // ── Validation ────────────────────────────────────────────────────────
        if (empty($identifier_raw) || ! in_array($type, ['email', 'phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètres invalides.',
                'code'    => 'INVALID_FORMAT',
            ], 422);
        }

        if (! preg_match('/^[0-9]{6}$/', $code_input)) {
            return response()->json([
                'success' => false,
                'message' => 'Le code doit contenir exactement 6 chiffres.',
                'code'    => 'INVALID_FORMAT',
            ], 422);
        }

        $identifier = $type === 'email'
            ? strtolower(trim($identifier_raw))
            : $this->normalizePhone($identifier_raw);

        if (! $identifier) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètres invalides.',
                'code'    => 'INVALID_FORMAT',
            ], 422);
        }

        // ── Récupérer l'OTP actif ─────────────────────────────────────────────
        $otpRow = DB::selectOne(
            'SELECT * FROM password_reset_otps
             WHERE identifier      = ?
               AND identifier_type = ?
               AND used            = false
               AND expires_at      > ?
             ORDER BY created_at DESC
             LIMIT 1',
            [$identifier, $type, now()->toDateTimeString()]
        );

        if (! $otpRow) {
            return response()->json([
                'success' => false,
                'message' => 'Code expiré ou invalide. Demandez un nouveau code.',
                'code'    => 'OTP_EXPIRED',
            ], 422);
        }

        // ── Vérifier le nombre de tentatives ──────────────────────────────────
        if ((int) $otpRow->attempts >= PasswordResetOtp::MAX_ATTEMPTS) {
            DB::statement('DELETE FROM password_reset_otps WHERE id = ?', [$otpRow->id]);

            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives. Demandez un nouveau code.',
                'code'    => 'MAX_ATTEMPTS',
            ], 422);
        }

        // ── Vérifier le code ──────────────────────────────────────────────────
        if ($otpRow->code !== $code_input) {
            DB::statement(
                'UPDATE password_reset_otps SET attempts = attempts + 1, updated_at = ? WHERE id = ?',
                [now()->toDateTimeString(), $otpRow->id]
            );

            $remaining = max(0, PasswordResetOtp::MAX_ATTEMPTS - ((int) $otpRow->attempts + 1));

            return response()->json([
                'success'            => false,
                'message'            => "Code incorrect. {$remaining} tentative(s) restante(s).",
                'code'               => 'WRONG_CODE',
                'attempts_remaining' => $remaining,
            ], 422);
        }

        // ── Code correct : marquer comme utilisé + générer verify_token ────────
        $verifyToken          = hash('sha256', $identifier . $type . now()->timestamp . random_int(1000, 9999));
        $verifyTokenExpiresAt = now()->addMinutes(60)->toDateTimeString();

        DB::statement(
            'UPDATE password_reset_otps
             SET used                    = true,
                 verify_token            = ?,
                 verify_token_expires_at = ?,
                 updated_at              = ?
             WHERE id = ?',
            [$verifyToken, $verifyTokenExpiresAt, now()->toDateTimeString(), $otpRow->id]
        );

        // ── Confirmer que le token est bien enregistré ────────────────────────
        $saved = DB::selectOne(
            'SELECT verify_token FROM password_reset_otps WHERE id = ?',
            [$otpRow->id]
        );

        if (! $saved || $saved->verify_token !== $verifyToken) {
            Log::error('[VerifyContact] verify_token non sauvegardé', ['id' => $otpRow->id]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur. Réessayez.',
                'code'    => 'SERVER_ERROR',
            ], 500);
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Contact vérifié avec succès.',
            'verify_token' => $verifyToken,
            'expires_in'   => 3600,
        ]);
    }

    // =========================================================================
    // Helpers privés
    // =========================================================================

    /**
     * Envoie le code via le(s) canal(aux) approprié(s).
     * Retourne true si au moins un canal a réussi.
     */
    private function dispatchCode(string $type, string $identifier, string $code): bool
    {
        if ($type === 'email') {
            try {
                $this->sendEmail($identifier, $code);
                return true;
            } catch (\Exception $e) {
                Log::error('[VerifyContact] Envoi email échoué', ['error' => $e->getMessage()]);
                return false;
            }
        }

        // Phone : SMS + WhatsApp en parallèle, au moins un doit réussir
        $smsSent      = false;
        $whatsappSent = false;

        try {
            $smsSent = app(\App\Services\SmsService::class)->sendOtp($identifier, $code, '');
        } catch (\Exception $e) {
            Log::warning('[VerifyContact] SMS échoué', ['error' => $e->getMessage()]);
        }

        try {
            $minutes = PasswordResetOtp::TTL_MINUTES;
            $message = "*Votre code de vérification CarEasy : {$code}*\n\n"
                     . "Valide pendant *{$minutes} minutes*.\n"
                     . "Ne partagez jamais ce code.";

            $whatsappSent = app(\App\Services\WhatsAppService::class)->sendMessage($identifier, $message);
        } catch (\Exception $e) {
            Log::warning('[VerifyContact] WhatsApp échoué', ['error' => $e->getMessage()]);
        }

        Log::info('[VerifyContact] Envoi code', [
            'sms'      => $smsSent      ? 'OK' : 'ÉCHEC',
            'whatsapp' => $whatsappSent ? 'OK' : 'ÉCHEC',
            'phone'    => $this->redact($identifier),
        ]);

        return $smsSent || $whatsappSent;
    }

    private function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if (empty($digits)) return null;

        // Retirer le code pays Bénin s'il est présent
        if (str_starts_with($digits, '229')) {
            $digits = substr($digits, 3);
        }

        // Format local 10 chiffres commençant par 0 → +229XXXXXXXXXX
        if (preg_match('/^0\d{9}$/', $digits)) {
            return '+229' . $digits;
        }

        // Format local 8 chiffres → +22901XXXXXXXX (migration automatique)
        if (preg_match('/^\d{8}$/', $digits)) {
            return '+22901' . $digits;
        }

        return null;
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

    private function emailHtml(string $code, int $minutes): string  {
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

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) return '***';
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

    /** Tronque un identifiant pour les logs. */
    private function redact(string $identifier): string
    {
        return substr($identifier, 0, 5) . '***';
    }
}