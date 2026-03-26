<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class QrLoginToken extends Model{
    protected $fillable = [
        'user_id',
        'token',
        'status',
        'expires_at',
        'used_by_device',
        'used_by_ip',
        'used_at',
        'new_auth_token',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                     ->where('expires_at', '>', Carbon::now());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Génère un nouveau token unique et non expiré.
     */
    public static function generateFor(User $user, int $ttlSeconds = 120): self
    {
        // Invalider les anciens tokens pending de cet utilisateur
        static::where('user_id', $user->id)
              ->where('status', 'pending')
              ->update(['status' => 'expired']);

        return static::create([
            'user_id'    => $user->id,
            'token'      => Str::random(64),
            'status'     => 'pending',
            'expires_at' => Carbon::now()->addSeconds($ttlSeconds),
        ]);
    }

    /**
     * Indique si le token est encore utilisable.
     */
    public function isValid(): bool
    {
        return $this->status === 'pending'
            && $this->expires_at->isFuture();
    }

    /**
     * Marque le token comme utilisé et enregistre les infos de l'appareil.
     */
    public function markUsed(string $deviceName, string $ip, string $newAuthToken): void
    {
        $this->update([
            'status'         => 'used',
            'used_by_device' => $deviceName,
            'used_by_ip'     => $ip,
            'used_at'        => Carbon::now(),
            'new_auth_token' => $newAuthToken,
        ]);
    }
}