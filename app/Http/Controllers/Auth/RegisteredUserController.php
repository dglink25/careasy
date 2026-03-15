<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

use Twilio\Rest\Client;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        // Étape 1: Vérifier qu'on a soit email soit téléphone, mais pas les deux
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

        // Étape 2: Nettoyer le téléphone si présent
        if ($hasPhone) {
            $cleanPhone = $this->cleanPhoneNumber($request->phone);
            $request->merge(['phone' => $cleanPhone]);
        }

        // Étape 3: Définir les règles de validation de base
        $rules = [
            'name' => ['required', 'string', 'max:255', 'min:2'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];

        // Étape 4: Ajouter les règles conditionnelles avec validation d'unicité stricte
        if ($hasEmail) {
            // Vérifier que l'email n'est pas déjà utilisé comme email OU comme téléphone (pour éviter les doublons)
            $rules['email'] = [
                'required', 
                'string', 
                'lowercase', 
                'email', 
                'max:255',
                function ($attribute, $value, $fail) {
                    // Vérifier si l'email existe déjà comme email
                    if (User::where('email', $value)->exists()) {
                        $fail('Cet email est déjà utilisé.');
                    }
                    
                    // Vérifier si l'email n'est pas déjà utilisé comme téléphone (cas improbable mais sécurisé)
                    if (User::where('phone', $value)->exists()) {
                        $fail('Cette valeur est déjà utilisée comme numéro de téléphone.');
                    }
                },
            ];
        } 
        elseif ($hasPhone) {
            $rules['phone'] = [
                'required', 
                'string', 
                'max:255',
                function ($attribute, $value, $fail) {
                    // Vérifier le format
                    if (!preg_match('/^[0-9+]{8,15}$/', $value)) {
                        $fail('Le format du numéro de téléphone est invalide. Utilisez uniquement des chiffres et éventuellement + au début.');
                    }
                    
                    // Vérifier la longueur
                    if (strlen($value) < 8) {
                        $fail('Le numéro de téléphone doit contenir au moins 8 chiffres.');
                    }
                    
                    if (strlen($value) > 15) {
                        $fail('Le numéro de téléphone ne doit pas dépasser 15 chiffres.');
                    }
                    
                    // Vérifier l'unicité stricte : ni comme téléphone, ni comme email
                    if (User::where('phone', $value)->exists()) {
                        $fail('Ce numéro de téléphone est déjà utilisé.');
                    }
                    
                    // Vérifier que le téléphone n'est pas déjà utilisé comme email (cas improbable mais sécurisé)
                    if (User::where('email', $value)->exists()) {
                        $fail('Cette valeur est déjà utilisée comme adresse email.');
                    }
                },
            ];
        }

        // Étape 5: Valider la requête
        try {
            $validatedData = $request->validate($rules);
        } catch (ValidationException $e) {
            // Personnaliser les messages d'erreur si nécessaire
            throw $e;
        }

        // Étape 6: Créer l'utilisateur
        $userData = [
            'name' => $validatedData['name'],
            'password' => Hash::make($validatedData['password']),
            'role' => 'client',
        ];

        // Ajouter email ou téléphone selon le cas
        if ($hasEmail) {
            $userData['email'] = $validatedData['email'];
            $userData['email_verified_at'] = null;
            $userData['phone'] = null;
            $userData['phone_verified_at'] = now(); // Pas de téléphone, donc considéré comme vérifié
        } else {
            $userData['phone'] = $validatedData['phone'];
            $userData['phone_verified_at'] = null;
            $userData['email'] = null;
            $userData['email_verified_at'] = now(); // Pas d'email, donc considéré comme vérifié
        }

        // Créer l'utilisateur
        $user = User::create($userData);

        // Étape 7: Créer le token API
        $token = $user->createToken('auth_token')->plainTextToken;

        // Étape 8: Déclencher l'événement Registered
        event(new Registered($user));

        // Étape 9: Connecter l'utilisateur
        Auth::login($user);

        $sid = env('TWILIO_ACCOUNT_SID');
        $tokenTwilio = env('TWILIO_AUTH_TOKEN');
        $twilio = new Client($sid, $tokenTwilio);
        $message = $twilio->messages
        ->create("+2290194119476", // to
            array(
            "messagingServiceSid" => "MGb1f770e5197be92255c57a07d25094f7",
            "body" => "Bonjour, votre inscription sur Careasy a été réussie. Bienvenue parmi nous !"
            )
        );


        // Étape 10: Retourner la réponse
        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'created_at' => $user->created_at,
            ],
            'token' => $token,
        ], 201);
    }

    public function checkEmail(Request $request){
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Vérifier l'unicité stricte
        $exists = User::where('email', $request->email)
                     ->orWhere('phone', $request->email)
                     ->exists();

        return response()->json([
            'available' => !$exists,
            'message' => !$exists ? 'Email disponible' : 'Email déjà utilisé'
        ]);
    }

    public function checkPhone(Request $request)  {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $cleanPhone = $this->cleanPhoneNumber($request->phone);

        // Vérifier l'unicité stricte (ni comme téléphone, ni comme email)
        $exists = User::where('phone', $cleanPhone)
                     ->orWhere('email', $cleanPhone)
                     ->exists();

        return response()->json([
            'available' => !$exists,
            'message' => !$exists ? 'Téléphone disponible' : 'Téléphone déjà utilisé'
        ]);
    }

    private function cleanPhoneNumber($phone) {
        // Garder seulement les chiffres et le + au début
        $clean = preg_replace('/[^0-9+]/', '', $phone);
        
        // S'assurer qu'il n'y a qu'un seul + au début
        if (substr_count($clean, '+') > 1) {
            $clean = preg_replace('/\+/', '', $clean);
            $clean = '+' . $clean;
        }
        
        return $clean;
    }
}