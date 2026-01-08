<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Message extends Model {
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'content',
        'latitude',
        'longitude',
        'read_at',
        'type',
        'file_path'
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    // ✅ IMPORTANT: Ajouter file_url aux attributs retournés
    protected $appends = ['file_url'];

    public function conversation() {
        return $this->belongsTo(Conversation::class);
    }

    public function sender() {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ✅ CORRECTION: Accessor pour retourner l'URL complète du fichier
    protected function fileUrl(): Attribute {
        return Attribute::get(function () {
            if (!$this->file_path) {
                return null;
            }
            
            // Retourner l'URL complète accessible publiquement
            return Storage::disk('public')->url($this->file_path);
        });
    }
}