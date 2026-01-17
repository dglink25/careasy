<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;

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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    /**
     * Accès rapide aux paramètres
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

        $settings = $value ? json_decode($value, true) : [];
        return array_merge($defaultSettings, $settings);
    }

    /**
     * Accès rapide au thème
     */
    public function getThemeAttribute()
    {
        return $this->settings['theme'] ?? 'light';
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