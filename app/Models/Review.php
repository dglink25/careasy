<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model{
    use HasFactory;

    protected $fillable = [
        'rendez_vous_id',
        'client_id',
        'prestataire_id',
        'rating',
        'comment',
        'reported',
        'report_reason',
        'reported_at'
    ];

    protected $casts = [
        'rating' => 'integer',
        'reported' => 'boolean',
        'reported_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relations
    public function rendezVous()
    {
        return $this->belongsTo(RendezVous::class, 'rendez_vous_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function prestataire()
    {
        return $this->belongsTo(User::class, 'prestataire_id');
    }
}