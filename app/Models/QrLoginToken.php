<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QrLoginToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'status',
        'expires_at',
        'used_by_device',
        'used_by_ip',
        'used_sanctum_token',
        'used_at',
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

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Générer un token unique de 64 caractères (hexadécimal).
     */
    public static function generateFor(User $user, int $ttlSeconds = 120): self
    {
        // Invalider les anciens tokens pending du même utilisateur
        self::where('user_id', $user->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        return self::create([
            'user_id'    => $user->id,
            'token'      => bin2hex(random_bytes(32)), // 64 hex chars
            'status'     => 'pending',
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);
    }

    public function markUsed(string $deviceName, string $ip, string $sanctumToken): void
    {
        $this->update([
            'status'             => 'used',
            'used_by_device'     => $deviceName,
            'used_by_ip'         => $ip,
            'used_sanctum_token' => $sanctumToken,
            'used_at'            => now(),
        ]);
    }
}