<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'content',
        'latitude',
        'longitude',
        'read_at',
        'type',
        'file_path',
        'temporary_id',
        'reply_to_id',   // ✅ AJOUTÉ
    ];

    protected $casts = [
        'read_at'    => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['file_url'];

    // ── Relations ──────────────────────────────────────────────────────────

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ✅ AJOUTÉ — message auquel celui-ci répond
    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id')
                    ->with(['sender:id,name,profile_photo_path']);
    }

    // ── Accesseurs ─────────────────────────────────────────────────────────

    protected function fileUrl(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->file_path) return null;
            if (filter_var($this->file_path, FILTER_VALIDATE_URL)) return $this->file_path;
            return Storage::disk('public')->url($this->file_path);
        });
    }
}