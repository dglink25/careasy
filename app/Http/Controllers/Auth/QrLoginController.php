<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\QrLoginToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * QrLoginController
 * ─────────────────
 * Gère le cycle de vie complet de la connexion rapide par QR code :
 *
 *  1. POST /user/sessions/share-token          → Génère un QR token (appareil source)
 *  2. GET  /user/sessions/share-token/{t}/status → Polling status (appareil source)
 *  3. POST /auth/qr-login                      → Échange le token contre une session (nouvel appareil)
 *
 * Flux :
 *  Appareil A (connecté) ──génère QR──▶ share_token (valide 2 min)
 *  Appareil B (non connecté) ──scanne──▶ POST /auth/qr-login { share_token }
 *                                       ◀── { token, user } (session Sanctum créée)
 *  Appareil A ──polling──▶ GET /status ◀── { status: "used", device_name: "..." }
 */
class QrLoginController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    //  1. GÉNÉRER UN QR TOKEN
    //  POST /user/sessions/share-token
    //  Auth : Sanctum (appareil déjà connecté)
    // ══════════════════════════════════════════════════════════════════════

    public function generate(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'expires_in' => 'nullable|integer|min:30|max:300',
        ]);

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
    //  Auth : Sanctum (même appareil qui a généré)
    // ══════════════════════════════════════════════════════════════════════

    public function status(Request $request, string $token): JsonResponse {
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
            'status'     => $qrToken->status,            // pending | used | expired
            'used'       => $qrToken->status === 'used',
            'expires_at' => $qrToken->expires_at->toIso8601String(),
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
    //  Auth : aucune (nouvel appareil non connecté)
    // ══════════════════════════════════════════════════════════════════════

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'share_token' => 'required|string|size:64',
        ]);

        $shareToken = $request->input('share_token');

        // Chercher le token valide
        $qrToken = QrLoginToken::where('token', $shareToken)
                               ->where('status', 'pending')
                               ->first();

        // Token inexistant
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

        // Token expiré
        if ($qrToken->expires_at->isPast()) {
            $qrToken->update(['status' => 'expired']);
            return response()->json([
                'message' => 'Ce QR code a expiré. Générez-en un nouveau.',
                'code'    => 'EXPIRED_TOKEN',
            ], 422);
        }

        $user = $qrToken->user;

        if (!$user) {
            Log::error('[QrLogin] Utilisateur introuvable pour le token', ['id' => $qrToken->user_id]);
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        try {
            // Détecter le nom de l'appareil depuis le User-Agent
            $deviceName = $this->resolveDeviceName($request);

            // Créer un token Sanctum pour le nouvel appareil
            $newSanctumToken = $user->createToken(
                'qr_login_' . now()->timestamp
            )->plainTextToken;

            // Marquer le QR token comme utilisé
            $qrToken->markUsed($deviceName, $request->ip(), $newSanctumToken);

            // Mettre à jour last_seen_at
            $user->update(['last_seen_at' => now()]);

            Log::info('[QrLogin] Connexion réussie', [
                'user_id'     => $user->id,
                'device'      => $deviceName,
                'ip'          => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'token'   => $newSanctumToken,
                'user'    => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role'  => $user->role,
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
    //  4. NETTOYAGE — purge les tokens expirés (commande artisan ou scheduler)
    //  Appelable aussi via POST /admin/qr-tokens/purge (optionnel)
    // ══════════════════════════════════════════════════════════════════════

    public function purgeExpired(): JsonResponse
    {
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

    /**
     * Tente de lire un nom d'appareil depuis les headers ou le User-Agent.
     * Ordre de priorité :
     *   1. Header X-Device-Name (envoyé volontairement par l'app Flutter)
     *   2. User-Agent simplifié
     */
    private function resolveDeviceName(Request $request): string
    {
        // Header personnalisé envoyé par l'app mobile
        if ($request->hasHeader('X-Device-Name')) {
            $name = trim($request->header('X-Device-Name'));
            if (!empty($name)) {
                return mb_substr($name, 0, 255);
            }
        }

        // Fallback : analyse du User-Agent
        $ua = $request->userAgent() ?? 'Appareil inconnu';

        if (str_contains($ua, 'Android')) {
            // Extraire le modèle Android ex: "SM-G996B Build/SP1A…"
            if (preg_match('/\(Linux; Android[\d. ]*;([^;)]+)/i', $ua, $m)) {
                return 'Android • ' . trim($m[1]);
            }
            return 'Android';
        }

        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
            return 'iPhone / iPad';
        }

        if (str_contains($ua, 'Windows')) return 'Windows';
        if (str_contains($ua, 'Mac'))     return 'Mac';
        if (str_contains($ua, 'Linux'))   return 'Linux';

        return mb_substr($ua, 0, 80);
    }
}