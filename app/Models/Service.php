<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model{
    use HasFactory;

    protected $fillable = [
        'entreprise_id',
        'prestataire_id',
        'domaine_id',
        'name',
        'start_time',
        'end_time',
        'price',
        'descriptions',
        'medias',
        'is_open_24h'
    ];
    protected $casts = [
        'medias' => 'array',
        'is_open_24h' => 'boolean',
    ];

    public function entreprise(){
        return $this->belongsTo(Entreprise::class);
    }

    public function domaine(){
        return $this->belongsTo(Domaine::class);
    }

    public function prestataire(){
        return $this->belongsTo(User::class, 'prestataire_id');
    }
}
