<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;



class SessionController extends Controller{

    public function index(Request $request): JsonResponse {
        $user           = Auth::user();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        // Charger tous les tokens Sanctum actifs de l'utilisateur
        $tokens = $user->tokens()
            ->whereNull('expires_at')
            ->orWhere('expires_at', '>', now())
            ->orderByDesc('last_used_at')
            ->get();

        $sessions = $tokens->map(function ($token) use ($currentTokenId) {
            $isCurrent  = $token->id === $currentTokenId;
            $deviceInfo = $this->parseDeviceName($token->name);

            return [
                'id'            => $token->id,
                'device_type'   => $deviceInfo['label'],
                'device_icon'   => $deviceInfo['type'],
                'device_name'   => $token->name,
                'ip_address'    => $token->ip_address ?? null,
                'location'      => $token->location ?? null,
                'last_used_at'  => $token->last_used_at?->toIso8601String(),
                'created_at'    => $token->created_at->toIso8601String(),
                'is_current'    => $isCurrent,
                'expires_at'    => $token->expires_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'sessions'  => $sessions,
            'total'     => $sessions->count(),
            'max'       => 5,
            'current_token_id' => $currentTokenId,
        ]);
    }


    public function revoke(Request $request, int $tokenId): JsonResponse {
        $user           = Auth::user();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        // On ne peut pas révoquer sa propre session depuis cette route
        if ($tokenId === $currentTokenId) {
            return response()->json([
                'message' => 'Utilisez /logout pour vous déconnecter de cet appareil.',
                'code'    => 'CANNOT_REVOKE_CURRENT',
            ], 422);
        }

        $token = $user->tokens()->where('id', $tokenId)->first();

        if (!$token) {
            return response()->json(['message' => 'Session introuvable.'], 404);
        }

        $token->delete();

        Log::info('[Session] Token révoqué', [
            'user_id'  => $user->id,
            'token_id' => $tokenId,
            'ip'       => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Session révoquée avec succès.',
        ]);
    }
    

    public function logoutAll(Request $request): JsonResponse {
        $user = Auth::user();

        // Supprimer TOUS les tokens Sanctum de l'utilisateur
        $count = $user->tokens()->delete();

        Log::info('[Session] Tous les tokens révoqués', [
            'user_id' => $user->id,
            'count'   => $count,
            'ip'      => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tous les appareils ont été déconnectés.',
            'revoked' => $count,
        ]);
    }

    public function loginHistory(Request $request): JsonResponse{
        $user = Auth::user();

        $history = LoginHistory::forUser($user->id)
            ->recent(30)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn($h) => [
                'id'          => $h->id,
                'success'     => $h->success,
                'ip_address'  => $h->ip_address,
                'device'      => $h->device,
                'location'    => $h->location,
                'method'      => $h->method,
                'fail_reason' => $h->fail_reason,
                'created_at'  => $h->created_at->toIso8601String(),
                'logged_at'   => $h->created_at->toIso8601String(),
            ]);

        $stats = [
            'total'   => $history->count(),
            'success' => $history->where('success', true)->count(),
            'failed'  => $history->where('success', false)->count(),
        ];

        return response()->json([
            'history' => $history,
            'stats'   => $stats,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  HELPER STATIQUE — Enregistrer une connexion (appelé depuis AuthController)
    //  Usage : SessionController::recordLogin($user, $request, true, 'email');
    // ══════════════════════════════════════════════════════════════════════

    public static function recordLogin(
        $user,
        Request $request,
        bool $success,
        string $method = 'email',
        ?string $failReason = null
    ): void {
        try {
            $ua         = $request->userAgent() ?? '';
            $deviceName = self::parseDeviceNameStatic($ua);
            $ip         = $request->ip();
            $location   = self::resolveLocation($ip);

            LoginHistory::create([
                'user_id'     => $user?->id ?? null,
                'success'     => $success,
                'ip_address'  => $ip,
                'device'      => $deviceName,
                'location'    => $location,
                'method'      => $method,
                'fail_reason' => $failReason,
                'user_agent'  => mb_substr($ua, 0, 255),
            ]);
        } catch (\Exception $e) {
            Log::error('[Session] Erreur enregistrement login history', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ── Helpers privés ────────────────────────────────────────────────────

    private function parseDeviceName(string $tokenName): array
    {
        return self::parseDeviceNameAsArray($tokenName);
    }

    public static function parseDeviceNameStatic(string $ua): string
    {
        if (str_contains($ua, 'Android')) {
            if (preg_match('/\(Linux; Android[\d. ]*;([^;)]+)/i', $ua, $m)) {
                return 'Android • ' . trim($m[1]);
            }
            return 'Android';
        }
        if (str_contains($ua, 'iPhone'))   return 'iPhone';
        if (str_contains($ua, 'iPad'))     return 'iPad';
        if (str_contains($ua, 'Windows'))  return 'Windows';
        if (str_contains($ua, 'Macintosh')) return 'Mac';
        if (str_contains($ua, 'Linux'))    return 'Linux';
        return mb_substr($ua, 0, 80) ?: 'Appareil inconnu';
    }

    private static function parseDeviceNameAsArray(string $name): array
    {
        $lower = strtolower($name);

        if (str_contains($lower, 'android') || str_contains($lower, 'mobile')) {
            return ['label' => $name, 'type' => 'mobile'];
        }
        if (str_contains($lower, 'iphone') || str_contains($lower, 'ipad')) {
            return ['label' => $name, 'type' => 'apple'];
        }
        if (str_contains($lower, 'windows') || str_contains($lower, 'mac') || str_contains($lower, 'linux')) {
            return ['label' => $name, 'type' => 'desktop'];
        }
        if (str_contains($lower, 'qr_login')) {
            return ['label' => 'Connexion QR', 'type' => 'qr'];
        }
        return ['label' => $name, 'type' => 'unknown'];
    }

    /**
     * Résolution de géolocalisation IP simplifiée.
     * En production, utiliser un service comme ip-api.com, MaxMind, etc.
     */
    private static function resolveLocation(string $ip): string
    {
        // IP locales / privées
        if (in_array($ip, ['127.0.0.1', '::1']) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return 'Réseau local';
        }

        // Optionnel : appel HTTP vers ip-api.com (désactiver en prod si trop lent)
        try {
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=city,country&lang=fr");
            if ($response) {
                $data = json_decode($response, true);
                if (!empty($data['city'])) {
                    return $data['city'] . ', ' . ($data['country'] ?? '');
                }
            }
        } catch (\Exception $_) {}

        return '';
    }
}