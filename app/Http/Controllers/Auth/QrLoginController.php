<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Auth\SessionController;
use App\Models\QrLoginToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class QrLoginController extends Controller{
    /** Nombre maximum de sessions simultanées par utilisateur */
    private const MAX_SESSIONS = 5;

 

    public function generate(Request $request): JsonResponse{
        $user = Auth::user();

        $request->validate([
            'expires_in' => 'nullable|integer|min:30|max:300',
        ]);

        // Vérifier la limite de sessions
        $sessionCount = $user->tokens()->count();
        if ($sessionCount >= self::MAX_SESSIONS) {
            return response()->json([
                'message' => 'Limite de 5 appareils atteinte. Révoquez un appareil pour en ajouter un nouveau.',
                'code'    => 'MAX_SESSIONS_REACHED',
                'current' => $sessionCount,
                'max'     => self::MAX_SESSIONS,
            ], 422);
        }

        $ttl = (int) ($request->input('expires_in', 120));

        try {
            $qrToken = QrLoginToken::generateFor($user, $ttl);

            Log::info('[QrLogin] Token généré', [
                'user_id'    => $user->id,
                'token'      => substr($qrToken->token, 0, 8) . '…',
                'expires_at' => $qrToken->expires_at->toIso8601String(),
            ]);

            return response()->json([
                'share_token' => $qrToken->token,
                'expires_in'  => $ttl,
                'expires_at'  => $qrToken->expires_at->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            Log::error('[QrLogin] Erreur génération token', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    //  2. VÉRIFIER LE STATUT D'UN TOKEN (polling)
    //  GET /user/sessions/share-token/{token}/status
    //  Auth : Sanctum
    // ══════════════════════════════════════════════════════════════════════

    public function status(Request $request, string $token): JsonResponse
    {
        $user = Auth::user();

        $qrToken = QrLoginToken::where('token', $token)
                               ->where('user_id', $user->id)
                               ->first();

        if (!$qrToken) {
            return response()->json(['message' => 'Token introuvable'], 404);
        }

        // Auto-expiration si dépassé
        if ($qrToken->status === 'pending' && $qrToken->expires_at->isPast()) {
            $qrToken->update(['status' => 'expired']);
        }

        $payload = [
            'status'       => $qrToken->status,
            'used'         => $qrToken->status === 'used',
            'expires_at'   => $qrToken->expires_at->toIso8601String(),
            'seconds_left' => max(0, (int) Carbon::now()->diffInSeconds($qrToken->expires_at, false)),
        ];

        if ($qrToken->status === 'used') {
            $payload['device_name'] = $qrToken->used_by_device ?? 'Appareil inconnu';
            $payload['used_at']     = $qrToken->used_at?->toIso8601String();
        }

        return response()->json($payload);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  3. ÉCHANGER LE TOKEN CONTRE UNE SESSION (scan du QR)
    //  POST /auth/qr-login
    //  Auth : aucune
    // ══════════════════════════════════════════════════════════════════════

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'share_token' => 'required|string|size:64',
        ]);

        $shareToken = $request->input('share_token');

        $qrToken = QrLoginToken::where('token', $shareToken)
                               ->where('status', 'pending')
                               ->first();

        if (!$qrToken) {
            Log::warning('[QrLogin] Token introuvable ou déjà utilisé', [
                'token_prefix' => substr($shareToken, 0, 8) . '…',
                'ip'           => $request->ip(),
            ]);
            return response()->json([
                'message' => 'QR code invalide ou déjà utilisé.',
                'code'    => 'INVALID_TOKEN',
            ], 422);
        }

        if ($qrToken->expires_at->isPast()) {
            $qrToken->update(['status' => 'expired']);
            return response()->json([
                'message' => 'Ce QR code a expiré. Générez-en un nouveau.',
                'code'    => 'EXPIRED_TOKEN',
            ], 422);
        }

        $user = $qrToken->user;

        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        // Vérifier la limite de sessions
        $sessionCount = $user->tokens()->count();
        if ($sessionCount >= self::MAX_SESSIONS) {
            return response()->json([
                'message' => 'Limite de 5 appareils atteinte. L\'utilisateur doit révoquer un appareil.',
                'code'    => 'MAX_SESSIONS_REACHED',
            ], 422);
        }

        try {
            $deviceName = $this->resolveDeviceName($request);

            $newSanctumToken = $user->createToken(
                'qr_login_' . now()->timestamp,
                ['*'],
                now()->addDays(90)
            )->plainTextToken;

            $qrToken->markUsed($deviceName, $request->ip(), $newSanctumToken);

            $user->update(['last_seen_at' => now()]);

            // Enregistrer dans l'historique des connexions
            SessionController::recordLogin($user, $request, true, 'qr');

            Log::info('[QrLogin] Connexion réussie', [
                'user_id' => $user->id,
                'device'  => $deviceName,
                'ip'      => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'token'   => $newSanctumToken,
                'user'    => [
                    'id'                => $user->id,
                    'name'              => $user->name,
                    'email'             => $user->email,
                    'phone'             => $user->phone,
                    'role'              => $user->role,
                    'profile_photo_url' => $user->profile_photo_url ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('[QrLogin] Erreur lors du login', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur interne du serveur'], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    //  4. NETTOYAGE
    //  POST /admin/qr-tokens/purge
    // ══════════════════════════════════════════════════════════════════════

    public function purgeExpired(): JsonResponse {
        $count = QrLoginToken::where('status', 'pending')
                             ->where('expires_at', '<', Carbon::now())
                             ->update(['status' => 'expired']);

        $deleted = QrLoginToken::where('status', 'expired')
                               ->where('updated_at', '<', Carbon::now()->subDays(7))
                               ->delete();

        return response()->json([
            'expired_marked' => $count,
            'old_deleted'    => $deleted,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function resolveDeviceName(Request $request): string
    {
        if ($request->hasHeader('X-Device-Name')) {
            $name = trim($request->header('X-Device-Name'));
            if (!empty($name)) return mb_substr($name, 0, 255);
        }

        $ua = $request->userAgent() ?? 'Appareil inconnu';

        if (str_contains($ua, 'Android')) {
            if (preg_match('/\(Linux; Android[\d. ]*;([^;)]+)/i', $ua, $m)) {
                return 'Android • ' . trim($m[1]);
            }
            return 'Android';
        }
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) return 'iPhone / iPad';
        if (str_contains($ua, 'Windows')) return 'Windows';
        if (str_contains($ua, 'Mac'))     return 'Mac';
        if (str_contains($ua, 'Linux'))   return 'Linux';

        return mb_substr($ua, 0, 80);
    }
}