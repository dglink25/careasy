<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\PasswordResetOtp;

class RegisteredUserController extends Controller{
    public function store(Request $request) {
        // Vérifier le token de contact vérifié avant tout
        $verifyToken = $request->input('verify_token');

        if (!$verifyToken) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez d\'abord vérifier votre email ou numéro de téléphone.',
                'code'    => 'CONTACT_NOT_VERIFIED',
            ], 422);
        }

        $cacheKey     = "contact_verified:{$verifyToken}";
        $verifiedData = cache()->get($cacheKey);

        if (!$verifiedData) {
            return response()->json([
                'success' => false,
                'message' => 'La vérification a expiré. Veuillez recommencer.',
                'code'    => 'VERIFY_TOKEN_EXPIRED',
            ], 422);
        }

        // Invalider le token immédiatement (usage unique)
        cache()->forget($cacheKey);

        // Forcer l'identifiant vérifié dans la requête
        if ($verifiedData['type'] === 'email') {
            $request->merge([
                'email' => $verifiedData['identifier'],
                'phone' => null,
            ]);
        } 
        else {
            $request->merge([
                'phone' => $verifiedData['identifier'],
                'email' => null,
            ]);
        }

        // Vérifier qu'on a soit email soit téléphone
        $hasEmail = $request->has('email') && !empty($request->email);
        $hasPhone = $request->has('phone') && !empty($request->phone);

        if (!$hasEmail && !$hasPhone) {
            throw ValidationException::withMessages([
                'contact' => ['Vous devez fournir soit un email, soit un numéro de téléphone.'],
            ]);
        }

        if ($hasEmail && $hasPhone) {
            throw ValidationException::withMessages([
                'email' => ['Vous ne pouvez pas fournir à la fois un email et un téléphone.'],
                'phone' => ['Vous ne pouvez pas fournir à la fois un téléphone et un email.'],
            ]);
        }

        // Nettoyer téléphone
        if ($hasPhone) {
            $cleanPhone = $this->cleanPhoneNumber($request->phone);
            $request->merge(['phone' => $cleanPhone]);
        }

        // Règles de validation
        $rules = [
            'name' => ['required', 'string', 'max:255', 'min:2'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];

        // Validation email
        if ($hasEmail) {
            $rules['email'] = [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (User::where('email', $value)->exists()) {
                        $fail('Cet email est déjà utilisé.');
                    }
                    if (User::where('phone', $value)->exists()) {
                        $fail('Cette valeur est déjà utilisée comme numéro de téléphone.');
                    }
                },
            ];
        }

        // Validation téléphone
        elseif ($hasPhone) {
            $rules['phone'] = [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/^[0-9+]{8,15}$/', $value)) {
                        $fail('Le format du numéro de téléphone est invalide.');
                    }
                    if (strlen($value) < 8) {
                        $fail('Le numéro doit contenir au moins 8 chiffres.');
                    }
                    if (strlen($value) > 15) {
                        $fail('Le numéro ne doit pas dépasser 15 chiffres.');
                    }
                    if (User::where('phone', $value)->exists()) {
                        $fail('Ce numéro de téléphone est déjà utilisé.');
                    }
                    if (User::where('email', $value)->exists()) {
                        $fail('Cette valeur est déjà utilisée comme adresse email.');
                    }
                },
            ];
        }

        // Validation
        $validatedData = $request->validate($rules);

        // Données utilisateur
        $userData = [
            'name'     => $validatedData['name'],
            'password' => Hash::make($validatedData['password']),
            'role'     => 'client',
        ];

        // Email ou téléphone
        if ($hasEmail) {
            $userData['email'] = $validatedData['email'];
            $userData['email_verified_at'] = now();
            $userData['phone'] = null;
            $userData['phone_verified_at'] = null;
        } else {
            $userData['phone'] = $validatedData['phone'];
            $userData['phone_verified_at'] = null;
            $userData['email'] = null;
            $userData['email_verified_at'] = null;
        }

        // Création utilisateur
        $user = User::create($userData);

        // Token API
        $token = $user->createToken('auth_token')->plainTextToken;

        // Event
        event(new Registered($user));

        // Login auto
        Auth::login($user);

        // Notifications d'inscription pour inscription par téléphone
        if ($hasPhone && !empty($user->phone)) {
            // SMS de bienvenue
            try {
                $sms = app(SmsService::class);
                $sms->notifyRegistration($user->phone, $user->name);
            } catch (\Exception $e) {
                Log::warning('[SMS] Notification inscription échouée : ' . $e->getMessage());
            }

            // WhatsApp de bienvenue (EN PLUS du SMS)
            try {
                $whatsApp = app(WhatsAppService::class);
                $whatsApp->notifyRegistration($user->phone, $user->name);
            } catch (\Exception $e) {
                Log::warning('[WhatsApp] Notification inscription échouée : ' . $e->getMessage());
            }
        }

        // Réponse finale
        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie',
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'phone'      => $user->phone,
                'role'       => $user->role,
                'created_at' => $user->created_at,
            ],
            'token' => $token,
        ], 201);
    }

    public function checkEmail(Request $request){
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $exists = User::where('email', $request->email)
            ->orWhere('phone', $request->email)
            ->exists();

        return response()->json([
            'available' => !$exists,
            'message'   => !$exists ? 'Email disponible' : 'Email déjà utilisé'
        ]);
    }

    public function checkPhone(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $cleanPhone = $this->cleanPhoneNumber($request->phone);

        $exists = User::where('phone', $cleanPhone)
            ->orWhere('email', $cleanPhone)
            ->exists();

        return response()->json([
            'available' => !$exists,
            'message'   => !$exists ? 'Téléphone disponible' : 'Téléphone déjà utilisé'
        ]);
    }

    private function cleanPhoneNumber($phone)
    {
        $clean = preg_replace('/[^0-9+]/', '', $phone);

        if (substr_count($clean, '+') > 1) {
            $clean = preg_replace('/\+/', '', $clean);
            $clean = '+' . $clean;
        }

        return $clean;
    }
}