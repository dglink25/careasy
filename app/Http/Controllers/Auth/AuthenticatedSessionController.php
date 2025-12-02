<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthenticatedSessionController extends Controller{
    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request){
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $user = User::where('email', $request->email)->first();
        
        // Révocation des anciens tokens pour plus de sécurité
        $user->tokens()->delete();
        
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
        // Vérifie si l'utilisateur est authentifié
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
            
            return response()->json([
                'message' => 'Logged out successfully'
            ], 200);
        }
        
        // Si pas d'utilisateur authentifié, on retourne quand même un succès
        return response()->json([
            'message' => 'Already logged out'
        ], 200);
    }
}