<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
        'google_formatted_address',
        'status_online',
        'whatsapp_phone',
        'call_phone',
        'trial_starts_at',
        'trial_ends_at',
        'has_used_trial',
        'max_services_allowed',
        'max_employees_allowed',
        'has_api_access'
    ];

    protected $casts = [
        'status_online' => 'boolean',
        'trial_starts_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'has_used_trial' => 'boolean',
        'has_api_access' => 'boolean',
        'max_services_allowed' => 'integer',
        'max_employees_allowed' => 'integer'
    ];

    protected $attributes = [
        'max_services_allowed' => 0,
        'max_employees_allowed' => 0,
        'has_api_access' => false,
        'has_used_trial' => false
    ];

    public function prestataire() {
        return $this->belongsTo(User::class, 'prestataire_id');
    }

    public function domaines() {
        return $this->belongsToMany(Domaine::class, 'entreprise_domaine');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function abonnements() {
        return $this->hasMany(Abonnement::class);
    }

    public function abonnementActif()  {
        return $this->hasOne(Abonnement::class)
            ->where('statut', 'actif')
            ->where('date_fin', '>', now());
    }


    public function activateTrialPeriod() {
        if ($this->has_used_trial) {
            return false;
        }

        $this->trial_starts_at = now();
        $this->trial_ends_at = now()->addDays(30);
        $this->has_used_trial = true;
        
        // Configuration de l'essai gratuit
        $this->max_services_allowed = 3;
        $this->max_employees_allowed = 1;
        $this->has_api_access = false;
        
        return $this->save();
    }

    /**
     * Vérifier si l'entreprise est en période d'essai
     */
    public function isInTrialPeriod()
    {
        if (!$this->trial_starts_at || !$this->trial_ends_at) {
            return false;
        }

        return now()->between($this->trial_starts_at, $this->trial_ends_at);
    }

    /**
     * Vérifier si la période d'essai est expirée
     */
    public function isTrialExpired()
    {
        if (!$this->trial_ends_at) {
            return false;
        }

        return now()->gt($this->trial_ends_at);
    }

    /**
     * Obtenir le nombre de jours restants dans l'essai
     */
    public function getTrialDaysRemainingAttribute()
    {
        if (!$this->isInTrialPeriod()) {
            return 0;
        }

        return now()->diffInDays($this->trial_ends_at, false);
    }

    /**
     * Vérifier si l'entreprise peut ajouter un nouveau service
     */
    public function canAddService()
    {
        if (!$this->isInTrialPeriod()) {
            return false;
        }

        return $this->services()->count() < $this->max_services_allowed;
    }

    /**
     * Vérifier si l'entreprise peut ajouter un employé
     */
    public function canAddEmployee()
    {
        if (!$this->isInTrialPeriod()) {
            return false;
        }

        // À implémenter selon votre logique de gestion des employés
        return true;
    }

    /**
     * Obtenir le statut textuel de l'essai
     */
    public function getTrialStatusAttribute()
    {
        if ($this->isInTrialPeriod()) {
            return [
                'status' => 'active',
                'message' => 'Période d\'essai en cours',
                'days_remaining' => $this->trial_days_remaining,
                'ends_at' => $this->trial_ends_at->format('d/m/Y')
            ];
        }

        if ($this->isTrialExpired()) {
            return [
                'status' => 'expired',
                'message' => 'Période d\'essai expirée',
                'ended_at' => $this->trial_ends_at->format('d/m/Y')
            ];
        }

        if ($this->has_used_trial) {
            return [
                'status' => 'used',
                'message' => 'Période d\'essai déjà utilisée'
            ];
        }

        return [
            'status' => 'available',
            'message' => 'Période d\'essai disponible'
        ];
    }
}