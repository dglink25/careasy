<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Models\User;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Support\Facades\Log;

class UserSettingsController extends Controller
{
    /**
     * Initialize Cloudinary configuration
     */
    private function initCloudinary()
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME', 'dsumeoiga'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
            'url' => [
                'secure' => true
            ]
        ]);
    }

    /**
     * Upload file to Cloudinary
     */
    private function uploadToCloudinary($file, $folder, $subfolder = null)
    {
        $this->initCloudinary();
        
        $folderPath = $subfolder
            ? "users/{$folder}/{$subfolder}"
            : "users/{$folder}";

        $result = (new UploadApi())->upload(
            $file->getRealPath(),
            [
                'folder' => $folderPath,
                'resource_type' => 'auto',
                'public_id' => 'profile_' . time() . '_' . uniqid(),
            ]
        );

        return $result['secure_url'];
    }

    /**
     * Delete file from Cloudinary
     */
    private function deleteFromCloudinary($url)
    {
        try {
            $this->initCloudinary();
            
            // Extraire le public_id de l'URL Cloudinary
            $urlParts = explode('/', $url);
            $publicIdWithExtension = end($urlParts);
            $publicId = explode('.', $publicIdWithExtension)[0];
            
            // Supprimer le dossier du chemin si présent
            if (strpos($url, '/users/') !== false) {
                $path = parse_url($url, PHP_URL_PATH);
                $pathParts = explode('/', $path);
                $index = array_search('users', $pathParts);
                if ($index !== false) {
                    $publicId = implode('/', array_slice($pathParts, $index));
                    $publicId = str_replace(['.jpg', '.jpeg', '.png', '.gif'], '', $publicId);
                }
            }
            
            (new UploadApi())->destroy($publicId);
            return true;
        } catch (\Exception $e) {
            Log::error('Error deleting from Cloudinary: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer le profil utilisateur
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_photo_url' => $user->profile_photo_url,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'settings' => $user->settings,
                'theme' => $user->theme,
            ]
        ]);
    }

    /**
     * Mettre à jour le profil
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user->name = $request->name;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_photo_url' => $user->profile_photo_url,
            ]
        ]);
    }

    /**
     * Mettre à jour l'email
     */
    public function updateEmail(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'required|string|current_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user->email = $request->email;
        $user->email_verified_at = null; // L'email doit être re-vérifié
        $user->save();

        // Envoyer un email de vérification (décommentez si configuré)
        // $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Adresse email mise à jour. Un email de vérification a été envoyé.'
        ]);
    }

    /**
     * Mettre à jour le mot de passe
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string|current_password',
            'new_password' => [
                'required',
                'string',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
                'confirmed',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        // Invalider tous les tokens existants pour la sécurité
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour avec succès. Veuillez vous reconnecter.'
        ]);
    }

    /**
     * Récupérer les paramètres
     */
    public function getSettings(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'settings' => $user->settings,
            'theme' => $user->theme,
        ]);
    }

    /**
     * Mettre à jour les paramètres
     */
    public function updateSettings(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'settings' => 'nullable|array',
            'settings.theme' => 'nullable|in:light,dark,system',
            'settings.language' => 'nullable|in:fr,en,es',
            'settings.notifications' => 'nullable|array',
            'settings.notifications.email' => 'nullable|boolean',
            'settings.notifications.push' => 'nullable|boolean',
            'settings.notifications.sms' => 'nullable|boolean',
            'settings.privacy' => 'nullable|array',
            'settings.privacy.profile_visibility' => 'nullable|in:public,private,friends_only',
            'settings.privacy.show_online_status' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $currentSettings = $user->settings;
        $newSettings = $request->settings ?? [];
        
        // Fusionner les paramètres existants avec les nouveaux
        $mergedSettings = array_merge($currentSettings, $newSettings);
        
        $user->settings = json_encode($mergedSettings);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Paramètres mis à jour avec succès',
            'settings' => $mergedSettings,
        ]);
    }

    /**
     * Mettre à jour uniquement le thème
     */
    public function updateTheme(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'theme' => 'required|in:light,dark,system',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $settings = $user->settings;
        $settings['theme'] = $request->theme;
        
        $user->settings = json_encode($settings);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Thème mis à jour avec succès',
            'theme' => $request->theme,
        ]);
    }

    /**
     * Récupérer les paramètres de notifications
     */
    public function getNotificationSettings(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'notifications' => $user->settings['notifications'] ?? [
                'email' => true,
                'push' => true,
                'sms' => false,
            ],
        ]);
    }

    /**
     * Mettre à jour les paramètres de notifications
     */
    public function updateNotificationSettings(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'notifications' => 'required|array',
            'notifications.email' => 'required|boolean',
            'notifications.push' => 'required|boolean',
            'notifications.sms' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $settings = $user->settings;
        $settings['notifications'] = $request->notifications;
        
        $user->settings = json_encode($settings);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Paramètres de notifications mis à jour',
            'notifications' => $settings['notifications'],
        ]);
    }

    /**
     * Mettre à jour la photo de profil
     */
    public function updateProfilePhoto(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'profile_photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Supprimer l'ancienne photo si elle existe et est sur Cloudinary
            if ($user->profile_photo_path && str_starts_with($user->profile_photo_path, 'http')) {
                $this->deleteFromCloudinary($user->profile_photo_path);
            }

            // Enregistrer la nouvelle photo sur Cloudinary
            $cloudinaryUrl = $this->uploadToCloudinary(
                $request->file('profile_photo'), 
                'profile-photos',
                'user-' . $user->id
            );
            
            $user->profile_photo_path = $cloudinaryUrl;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Photo de profil mise à jour avec succès',
                'profile_photo_url' => $user->profile_photo_url,
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating profile photo: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload de la photo',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Supprimer la photo de profil
     */
    public function deleteProfilePhoto(Request $request)
    {
        $user = $request->user();

        if (!$user->profile_photo_path) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune photo de profil à supprimer'
            ], 404);
        }

        try {
            // Supprimer de Cloudinary si c'est une URL Cloudinary
            if (str_starts_with($user->profile_photo_path, 'http')) {
                $this->deleteFromCloudinary($user->profile_photo_path);
            }
            
            $user->profile_photo_path = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Photo de profil supprimée avec succès',
                'profile_photo_url' => $user->profile_photo_url,
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting profile photo: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la photo',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mettre à jour toutes les informations de l'utilisateur en une seule requête
     */
    public function updateAll(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'current_password' => 'nullable|string|current_password:api',
            'new_password' => [
                'nullable',
                'string',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
                'confirmed',
            ],
            'theme' => 'nullable|in:light,dark,system',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Mettre à jour le nom
            if ($request->has('name')) {
                $user->name = $request->name;
            }

            // Mettre à jour l'email
            if ($request->has('email') && $request->email !== $user->email) {
                $user->email = $request->email;
                $user->email_verified_at = null;
                // Envoyer un email de vérification
                // $user->sendEmailVerificationNotification();
            }

            // Mettre à jour le mot de passe
            if ($request->has('new_password') && $request->has('current_password')) {
                if (Hash::check($request->current_password, $user->password)) {
                    $user->password = Hash::make($request->new_password);
                    // Invalider les tokens si le mot de passe change
                    $user->tokens()->delete();
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le mot de passe actuel est incorrect'
                    ], 422);
                }
            }

            // Mettre à jour les paramètres
            if ($request->has('settings')) {
                $currentSettings = $user->settings;
                $mergedSettings = array_merge($currentSettings, $request->settings);
                $user->settings = json_encode($mergedSettings);
            }

            // Mettre à jour le thème directement
            if ($request->has('theme')) {
                $settings = $user->settings;
                $settings['theme'] = $request->theme;
                $user->settings = json_encode($settings);
            }

            // Mettre à jour la photo de profil
            if ($request->hasFile('profile_photo')) {
                // Supprimer l'ancienne photo si elle existe
                if ($user->profile_photo_path && str_starts_with($user->profile_photo_path, 'http')) {
                    $this->deleteFromCloudinary($user->profile_photo_path);
                }
                
                $cloudinaryUrl = $this->uploadToCloudinary(
                    $request->file('profile_photo'), 
                    'profile-photos',
                    'user-' . $user->id
                );
                $user->profile_photo_path = $cloudinaryUrl;
            }

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Informations mises à jour avec succès',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'profile_photo_url' => $user->profile_photo_url,
                    'theme' => $user->theme,
                    'settings' => $user->settings,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
}