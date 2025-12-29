<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

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

    public function conversation() {
        return $this->belongsTo(Conversation::class);
    }


    public function sender() {
        return $this->belongsTo(User::class, 'sender_id');
    }

    protected function fileUrl(): Attribute{
        return Attribute::get(fn() => $this->file_path
            ? \Storage::disk('public')->url($this->file_path)
            : null);
    }
}