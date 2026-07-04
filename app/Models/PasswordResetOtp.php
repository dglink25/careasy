<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PasswordResetOtp extends Model{
    protected $fillable = [
        'identifier',
        'identifier_type',
        'code',
        'used',
        'expires_at',
        'attempts',
        'verify_token',
        'verify_token_expires_at',
    ];

    protected $casts = [
        'used'       => 'boolean',
        'expires_at' => 'datetime',
        'attempts'   => 'integer',
        'verify_token_expires_at' => 'datetime',
        
    ];

    // ── Constantes ────────────────────────────────────────────────────────────
    const TTL_MINUTES    = 5;   // Durée de validité du code
    const MAX_ATTEMPTS   = 5;   // Tentatives max avant invalidation
    const RESEND_DELAY   = 60;  // Secondes avant de pouvoir renvoyer un code

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeValid($query)
    {
        return $query->where('used', false)
                     ->where('expires_at', '>', now());
    }

    public function scopeForIdentifier($query, string $identifier, string $type)
    {
        return $query->where('identifier', $identifier)
                     ->where('identifier_type', $type);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    public function secondsLeft(): int
    {
        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    // ── Factory ───────────────────────────────────────────────────────────────

    /**
     * Génère un nouveau code OTP pour un identifiant donné.
     * Supprime les anciens codes non utilisés pour ce même identifiant.
     */
    public static function generateFor(string $identifier, string $type): self
    {
        // Supprimer les anciens codes pour cet identifiant
        self::where('identifier', $identifier)
            ->where('identifier_type', $type)
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Utiliser DB::insert pour contourner le problème de cast boolean/integer avec PostgreSQL
        \Illuminate\Support\Facades\DB::table('password_reset_otps')->insert([
            'identifier'      => $identifier,
            'identifier_type' => $type,
            'code'            => $code,
            'used'            => false,         // PDO::PARAM_BOOL via binding
            'expires_at'      => now()->addMinutes(self::TTL_MINUTES)->toDateTimeString(),
            'attempts'        => 0,
            'created_at'      => now()->toDateTimeString(),
            'updated_at'      => now()->toDateTimeString(),
        ]);

        return self::where('identifier', $identifier)
            ->where('identifier_type', $type)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * Vérifie le code saisi et incrémente les tentatives.
     * Retourne true si le code est correct.
     */
    public function verify(string $inputCode): bool
    {
        $this->increment('attempts');

        if ($this->code !== $inputCode) {
            return false;
        }

        $this->update(['used' => true]);
        return true;
    }
}