<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entreprise extends Model{
    use HasFactory;

    protected $fillable = [
        'name',
        'prestataire_id',
        'ifu_number',
        'ifu_file',
        'rccm_number',
        'rccm_file',
        'pdg_full_name',
        'pdg_full_profession',
        'role_user',
        'siege',
        'logo',
        'certificate_number',
        'certificate_file',
        'image_boutique',
        'status',
        'admin_note',
        'latitude',
        'longitude',
        'google_formatted_address'
    ];

    public function prestataire(){
        return $this->belongsTo(User::class, 'prestataire_id');
    }

    public function domaines(){
        return $this->belongsToMany(Domaine::class, 'entreprise_domaine');
    }

    public function services(){
        return $this->hasMany(Service::class);
    }
    
}
