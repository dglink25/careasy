<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request)
    {
        // Validation conditionnelle : soit email, soit téléphone
        $rules = [
            'password' => 'required|string|min:8',
        ];

        // Vérifier si c'est une connexion avec email
        if ($request->has('email') && !empty($request->email)) {
            $rules['email'] = 'required|string|email';
            
            // S'assurer que le téléphone n'est pas fourni
            if ($request->has('phone') && !empty($request->phone)) {
                throw ValidationException::withMessages([
                    'phone' => ['Veuillez utiliser soit l\'email, soit le téléphone, pas les deux.'],
                ]);
            }
        }
        // Vérifier si c'est une connexion avec téléphone
        elseif ($request->has('phone') && !empty($request->phone)) {
            $rules['phone'] = 'required|string';
            
            // Nettoyer le téléphone
            $request->merge([
                'phone' => preg_replace('/[^0-9+]/', '', $request->phone)
            ]);
            
            // S'assurer que l'email n'est pas fourni
            if ($request->has('email') && !empty($request->email)) {
                throw ValidationException::withMessages([
                    'email' => ['Veuillez utiliser soit le téléphone, soit l\'email, pas les deux.'],
                ]);
            }
        } else {
            // Ni email ni téléphone fourni
            throw ValidationException::withMessages([
                'login' => ['Veuillez fournir un email ou un numéro de téléphone.'],
            ]);
        }

        // Valider la requête
        $request->validate($rules);

        // Tentative de connexion selon la méthode
        $credentials = [];
        
        if ($request->has('email')) {
            $credentials['email'] = $request->email;
            $field = 'email';
        } else {
            $credentials['phone'] = $request->phone;
            $field = 'phone';
        }
        
        $credentials['password'] = $request->password;

        // Tentative d'authentification
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects. Vérifiez votre ' . 
                            ($field === 'email' ? 'email' : 'numéro de téléphone') . 
                            ' et votre mot de passe.'
            ], 401);
        }

        $user = User::where($field, $request->$field)->first();
        
        // Supprimer les anciens tokens
        $user->tokens()->delete();
        
        // Créer un nouveau token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
            ],
            'token' => $token
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Déconnecté avec succès',
            'google_logout_url' => 'https://accounts.google.com/Logout'
        ], 200);
    }

    /**
     * Vérifier si un utilisateur existe avec cet email
     */
    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'exists' => $exists,
        ]);
    }

    public function checkPhone(Request $request) {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $cleanPhone = preg_replace('/[^0-9+]/', '', $request->phone);
        $exists = User::where('phone', $cleanPhone)->exists();

        return response()->json([
            'exists' => $exists,
        ]);
    }
}