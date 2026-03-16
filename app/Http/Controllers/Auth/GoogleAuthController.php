<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Google\Client as GoogleClient;
class GoogleAuthController extends Controller{
    public function redirectToGoogle(){
        return Socialite::driver('google')
            ->redirectUrl(config('services.google.redirect'))
            ->stateless()
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request){
        try {
           
            $googleUser = Socialite::driver('google')
                ->redirectUrl(config('services.google.redirect'))
                ->stateless()
                ->user();

            // Vérifier si l'utilisateur existe déjà
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                // Créer un nouvel utilisateur
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'password' => Hash::make(Str::random(24)), // Mot de passe aléatoire
                    'google_id' => $googleUser->getId(),
                    'email_verified_at' => now(), // Email vérifié via Google
                    'role' => 'client', // Rôle par défaut
                ]);
            } 
            else {
                // Mettre à jour l'ID Google si nécessaire
                if (empty($user->google_id)) {
                    $user->update(['google_id' => $googleUser->getId()]);
                }
                
                // Mettre à jour la vérification d'email
                if (empty($user->email_verified_at)) {
                    $user->update(['email_verified_at' => now()]);
                }
            }

            // Connecter l'utilisateur
            Auth::login($user);

            $user->update(['last_seen_at' => now()]);

            // Générer un token Sanctum pour l'API
            $token = $user->createToken('google-auth-token')->plainTextToken;

            // Rediriger vers le frontend avec le token
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
            
            return redirect("$frontendUrl/auth/callback?token=" . $token . '&user=' . urlencode(json_encode([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ])));

        } 
        catch (\Exception $e) {
            // \Log::error('Google auth error: ' . $e->getMessage());
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
            return redirect("$frontendUrl/login?error=google_auth_failed&message=" . urlencode($e->getMessage()));
        }
    }


    public function handleGoogleCallbackMobile(Request $request){
        try {
            // Valider la requête
            $request->validate([
                'id_token' => 'required|string',
                'email' => 'required|email',
                'name' => 'nullable|string',
            ]);

            // Vérifier le token avec Google (optionnel mais recommandé)
            $client = new GoogleClient(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($request->id_token);
            
            if (!$payload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token invalide'
                ], 401);
            }

            // Vérifier que l'email correspond
            if ($payload['email'] !== $request->email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email ne correspond pas au token'
                ], 401);
            }

            // Vérifier si l'utilisateur existe déjà
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                // Créer un nouvel utilisateur
                $user = User::create([
                    'name' => $request->name ?? $payload['name'],
                    'email' => $request->email,
                    'password' => Hash::make(Str::random(24)),
                    'google_id' => $payload['sub'],
                    'email_verified_at' => now(),
                    'role' => 'client',
                ]);
            } else {
                // Mettre à jour l'ID Google si nécessaire
                if (empty($user->google_id)) {
                    $user->update(['google_id' => $payload['sub']]);
                }
                
                // Mettre à jour la vérification d'email
                if (empty($user->email_verified_at)) {
                    $user->update(['email_verified_at' => now()]);
                }
            }

            // Connecter l'utilisateur
            Auth::login($user);

            // Générer un token Sanctum pour l'API
            $token = $user->createToken('google-auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]
            ]);

        } catch (\Exception $e) {
            //Log::error('Google auth error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}