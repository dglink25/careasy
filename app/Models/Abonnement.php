<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Abonnement extends Model{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'user_id',
        'plan_id',
        'paiement_id',
        'entreprise_id',
        'date_debut',
        'date_fin',
        'statut',
        'renouvellement_auto',
        'date_annulation',
        'motif_annulation',
        'metadata'
    ];

    protected $casts = [
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
        'date_annulation' => 'datetime',
        'renouvellement_auto' => 'boolean',
        'metadata' => 'array'
    ];

    // Relations
    public function user()  {
        return $this->belongsTo(User::class);
    }

    public function plan() {
        return $this->belongsTo(Plan::class);
    }

    public function paiement()  {
        return $this->belongsTo(Paiement::class);
    }

    public function entreprise()  {
        return $this->belongsTo(Entreprise::class);
    }

    // Scopes
    public function scopeActif($query)  {
        return $query->where('statut', 'actif')
            ->where('date_fin', '>', now());
    }

    public function scopeExpire($query) {
        return $query->where('statut', 'actif')
            ->where('date_fin', '<=', now());
    }

    public function scopeParUser($query, $userId){
        return $query->where('user_id', $userId);
    }

    // Méthodes
    public function estActif() {
        return $this->statut === 'actif' && $this->date_fin->isFuture();
    }

    public function estExpire() {
        return $this->statut === 'actif' && $this->date_fin->isPast();
    }

    public function joursRestants() {
        if (!$this->estActif()) {
            return 0;
        }
        
        return now()->diffInDays($this->date_fin, false);
    }

    public function getJoursRestantsAttribute()  {
        return $this->joursRestants();
    }

    public function getPeriodeAttribute() {
        return $this->date_debut->format('d/m/Y') . ' - ' . $this->date_fin->format('d/m/Y');
    }

    public function getStatutLibelleAttribute() {
        return match($this->statut) {
            'actif' => 'Actif',
            'expire' => 'Expiré',
            'annule' => 'Annulé',
            'suspendu' => 'Suspendu',
            default => $this->statut
        };
    }

    public function getStatutColorAttribute()  {
        return match($this->statut) {
            'actif' => '#10b981',
            'expire' => '#ef4444',
            'annule' => '#f59e0b',
            'suspendu' => '#6b7280',
            default => '#6b7280'
        };
    }

    // Génération de référence unique
    public static function genererReference() {
        $prefix = 'SUB';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));
        
        return $prefix . $date . $random;
    }
}