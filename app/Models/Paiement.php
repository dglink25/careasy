<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paiement extends Model{
    use HasFactory;

    protected $fillable = [
        'reference',
        'user_id',
        'plan_id',
        'montant',
        'devise',
        'methode_paiement',
        'statut',
        'fedapay_response',
        'fedapay_transaction_id',
        'fedapay_status',
        'date_paiement'
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'fedapay_response' => 'array',
        'date_paiement' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function abonnement()
    {
        return $this->hasOne(Abonnement::class);
    }

    // Scopes
    public function scopeSucces($query)
    {
        return $query->where('statut', 'succes');
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopeParUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Méthodes
    public function estReussi()
    {
        return $this->statut === 'succes';
    }

    public function estEnAttente()
    {
        return $this->statut === 'en_attente';
    }

    public function getMontantFormateAttribute()
    {
        return number_format($this->montant, 0, ',', ' ') . ' ' . $this->devise;
    }

    public function getDatePaiementFormateeAttribute()
    {
        return $this->date_paiement ? $this->date_paiement->format('d/m/Y H:i') : null;
    }

    // Génération de référence unique
    public static function genererReference()
    {
        $prefix = 'PAY';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));
        
        return $prefix . $date . $random;
    }
}