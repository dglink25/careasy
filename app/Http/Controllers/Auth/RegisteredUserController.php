<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    // =========================================================================
    // POST /register
    // Body : verify_token, name, password, password_confirmation
    // =========================================================================
    public function store(Request $request)
    {
        // ── 1. Valider les champs soumis ──────────────────────────────────────
        $request->validate([
            'verify_token' => ['required', 'string'],
            'name'         => ['required', 'string', 'min:2', 'max:255'],
            'password'     => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // ── 2. Récupérer la ligne OTP liée au verify_token ────────────────────
        //    Conditions strictes :
        //    - verify_token correspond
        //    - verify_token_expires_at non expiré
        //    - used = true  (le code OTP a bien été validé dans /check)
        $otpRow = DB::selectOne(
            'SELECT * FROM password_reset_otps
             WHERE verify_token            = ?
               AND verify_token_expires_at > ?
               AND used                   = true
             LIMIT 1',
            [$request->verify_token, now()->toDateTimeString()]
        );

        $identifier = $otpRow->identifier;      // email ou téléphone normalisé
        $type       = $otpRow->identifier_type; // 'email' | 'phone'

        // ── 3. Vérifier que l'identifiant n'est pas déjà pris ─────────────────
        //    (peut arriver si deux inscriptions concurrentes avec le même contact)
        $column = $type === 'email' ? 'email' : 'phone';

        if (User::where($column, $identifier)->exists()) {
            $this->invalidateToken($otpRow->id);

            return response()->json([
                'success' => false,
                'message' => $type === 'email'
                    ? 'Cette adresse email est déjà associée à un compte.'
                    : 'Ce numéro de téléphone est déjà associé à un compte.',
                'code'    => 'ALREADY_USED',
            ], 422);
        }

        // ── 4. Construire les données utilisateur ─────────────────────────────
        $userData = [
            'name'     => $request->name,
            'password' => Hash::make($request->password),
            'role'     => 'client',
        ];

        if ($type === 'email') {
            $userData['email']             = $identifier;
            $userData['email_verified_at'] = now();
            $userData['phone']             = null;
            $userData['phone_verified_at'] = null;
        } else {
            $userData['phone']             = $identifier;
            $userData['phone_verified_at'] = now();
            $userData['email']             = null;
            $userData['email_verified_at'] = null;
        }

        // ── 5. Créer l'utilisateur ────────────────────────────────────────────
        $user = User::create($userData);

        // ── 6. Invalider immédiatement le token (usage unique) ────────────────
        $this->invalidateToken($otpRow->id);

        // ── 7. Token API Sanctum + login automatique ──────────────────────────
        $token = $user->createToken('auth_token')->plainTextToken;

        event(new Registered($user));
        Auth::login($user);

        // ── 8. Notifications de bienvenue (phone uniquement) ──────────────────
        if ($type === 'phone' && ! empty($user->phone)) {
            $this->sendWelcomeNotifications($user);
        }

        // ── 9. Réponse ────────────────────────────────────────────────────────
        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie',
            'user'    => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'phone'      => $user->phone,
                'role'       => $user->role,
                'created_at' => $user->created_at,
            ],
            'token'   => $token,
        ], 201);
    }

    /** Invalide le verify_token après utilisation (usage unique). */
    private function invalidateToken(int $otpId): void
    {
        try {
            DB::statement(
                'UPDATE password_reset_otps
                 SET verify_token = NULL, verify_token_expires_at = NULL
                 WHERE id = ?',
                [$otpId]
            );
        } catch (\Exception $e) {
            Log::warning('[Register] Impossible d\'invalider le verify_token', [
                'otp_id' => $otpId,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /** Envoie SMS + WhatsApp de bienvenue en fire-and-forget. */
    private function sendWelcomeNotifications(User $user): void
    {
        try {
            app(\App\Services\SmsService::class)->notifyRegistration($user->phone, $user->name);
        } catch (\Exception $e) {
            Log::warning('[SMS] Notification inscription échouée : ' . $e->getMessage());
        }

        try {
            app(\App\Services\WhatsAppService::class)->notifyRegistration($user->phone, $user->name);
        } catch (\Exception $e) {
            Log::warning('[WhatsApp] Notification inscription échouée : ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Utilitaires de disponibilité (appelés par le frontend en temps réel)
    // =========================================================================

    public function checkEmail(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $exists = User::where('email', $request->email)
            ->orWhere('phone', $request->email)
            ->exists();

        return response()->json([
            'available' => ! $exists,
            'message'   => ! $exists ? 'Email disponible' : 'Email déjà utilisé',
        ]);
    }

    public function checkPhone(Request $request)
    {
        $request->validate(['phone' => ['required', 'string']]);

        $clean  = $this->cleanPhoneNumber($request->phone);
        $exists = User::where('phone', $clean)
            ->orWhere('email', $clean)
            ->exists();

        return response()->json([
            'available' => ! $exists,
            'message'   => ! $exists ? 'Téléphone disponible' : 'Téléphone déjà utilisé',
        ]);
    }

    private function cleanPhoneNumber(string $phone): string
    {
        $clean = preg_replace('/[^0-9+]/', '', $phone);

        if (substr_count($clean, '+') > 1) {
            $clean = '+' . preg_replace('/\+/', '', $clean);
        }

        return $clean;
    }
}