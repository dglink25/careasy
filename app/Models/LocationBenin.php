<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LocationBenin extends Model {
    protected $table    = 'locations_benin';
    protected $fillable = ['code_admin','arrondissement','commune','departement','latitude','longitude'];
}