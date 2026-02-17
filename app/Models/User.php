<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;
use App\Notifications\CustomResetPasswordNotification;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'theme',
        'profile_photo_path',
        'settings',
        'google_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * ✅ IMPORTANT: Ne PAS caster 'settings' en array ici
     * Car on gère manuellement dans l'accessor
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // ❌ NE PAS METTRE 'settings' => 'array' ICI
        ];
    }

    public function sendPasswordResetNotification($token)
{
    $this->notify(new CustomResetPasswordNotification($token));
}

    /**
     * ✅ Accessor pour settings - Gestion manuelle du JSON
     */
    public function getSettingsAttribute($value)
    {
        $defaultSettings = [
            'theme' => 'light',
            'notifications' => [
                'email' => true,
                'push' => true,
                'sms' => false,
            ],
            'privacy' => [
                'profile_visibility' => 'public',
                'show_online_status' => true,
            ],
            'language' => 'fr',
        ];

        // Si la valeur est vide ou null
        if (empty($value) || $value === null) {
            return $defaultSettings;
        }

        // Si c'est déjà un tableau (ne devrait pas arriver)
        if (is_array($value)) {
            return array_merge($defaultSettings, $value);
        }

        // Décoder le JSON
        $decoded = json_decode($value, true);
        
        // Si le décodage échoue, retourner les valeurs par défaut
        if (!is_array($decoded)) {
            return $defaultSettings;
        }

        // Fusionner récursivement pour conserver la structure imbriquée
        return array_replace_recursive($defaultSettings, $decoded);
    }

    /**
     * ✅ Setter pour settings - Encoder en JSON
     */
    public function setSettingsAttribute($value)
    {
        if (is_string($value)) {
            // Si c'est déjà une string JSON, vérifier qu'elle est valide
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->attributes['settings'] = $value;
            } else {
                $this->attributes['settings'] = json_encode([]);
            }
        } elseif (is_array($value)) {
            // Si c'est un tableau, l'encoder en JSON
            $this->attributes['settings'] = json_encode($value);
        } else {
            // Sinon, utiliser un objet vide
            $this->attributes['settings'] = json_encode([]);
        }
    }

    /**
     * Accès rapide au thème
     */
    public function getThemeAttribute()
    {
        $settings = $this->settings;
        return $settings['theme'] ?? 'light';
    }

    /**
     * Vérifie si l'utilisateur a une photo de profil
     */
    public function hasProfilePhoto()
    {
        return !empty($this->profile_photo_path);
    }

    /**
     * URL de la photo de profil
     */
    public function getProfilePhotoUrlAttribute()
    {
        if ($this->profile_photo_path) {
            // Si c'est une URL Cloudinary (commence par http)
            if (str_starts_with($this->profile_photo_path, 'http')) {
                return $this->profile_photo_path;
            }
            // Sinon, c'est peut-être un chemin local
            return Storage::url($this->profile_photo_path);
        }
        
        // URL par défaut avec Avatar
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }
}