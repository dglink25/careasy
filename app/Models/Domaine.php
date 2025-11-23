<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domaine extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function entreprises(){
        return $this->belongsToMany(Entreprise::class, 'entreprise_domaine');
    }

    public function services(){
        return $this->hasMany(Service::class);
    }
}
