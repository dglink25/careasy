<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
        'price_promo',
        'is_price_on_request',
        'has_promo',
        'promo_start_date',
        'promo_end_date',
        'descriptions',
        'medias',
        'is_open_24h',
        'schedule',
        'is_always_open'
    ];

    protected $casts = [
        'medias' => 'array',
        'is_open_24h' => 'boolean',
        'is_always_open' => 'boolean',
        'is_price_on_request' => 'boolean',
        'has_promo' => 'boolean',
        'schedule' => 'array',
        'promo_start_date' => 'datetime',
        'promo_end_date' => 'datetime',
    ];

    const DAYS = [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ];

    // Accesseurs pour le prix formaté
    public function getFormattedPriceAttribute() {
        if ($this->is_price_on_request) {
            return 'Sur devis';
        }
        
        if ($this->has_promo && $this->isPromoActive()) {
            return number_format($this->price_promo, 0, ',', ' ') . ' FCFA';
        }
        
        return $this->price ? number_format($this->price, 0, ',', ' ') . ' FCFA' : 'Prix non défini';
    }

    public function getOriginalPriceAttribute()  {
        return $this->price ? number_format($this->price, 0, ',', ' ') . ' FCFA' : null;
    }

    public function getPromoPriceAttribute() {
        return $this->price_promo ? number_format($this->price_promo, 0, ',', ' ') . ' FCFA' : null;
    }

    // Vérifier si la promo est active
    public function isPromoActive() {
        if (!$this->has_promo || !$this->price_promo) {
            return false;
        }

        $now = Carbon::now();

        // Si pas de dates définies, la promo est toujours active
        if (!$this->promo_start_date && !$this->promo_end_date) {
            return true;
        }

        // Vérifier les dates
        if ($this->promo_start_date && $this->promo_end_date) {
            return $now->between($this->promo_start_date, $this->promo_end_date);
        }

        if ($this->promo_start_date) {
            return $now->gte($this->promo_start_date);
        }

        if ($this->promo_end_date) {
            return $now->lte($this->promo_end_date);
        }

        return false;
    }

    // Calculer le pourcentage de réduction
    public function getDiscountPercentageAttribute() {
        if (!$this->has_promo || !$this->price_promo || !$this->price || $this->price == 0) {
            return null;
        }

        $discount = (($this->price - $this->price_promo) / $this->price) * 100;
        return round($discount);
    }

    public function entreprise() {
        return $this->belongsTo(Entreprise::class);
    }

    public function domaine() {
        return $this->belongsTo(Domaine::class);
    }

    public function prestataire() {
        return $this->belongsTo(User::class, 'prestataire_id');
    }

    public function isAvailableAt($day, $time)  {
        if ($this->is_always_open) {
            return true;
        }

        if (!$this->schedule || !isset($this->schedule[$day])) {
            return false;
        }

        $daySchedule = $this->schedule[$day];
        
        if (!$daySchedule['is_open']) {
            return false;
        }

        return $time >= $daySchedule['start'] && $time <= $daySchedule['end'];
    }

    public function getFormattedSchedule() {
        if ($this->is_always_open) {
            return 'Toujours disponible';
        }

        if (!$this->schedule) {
            return $this->is_open_24h ? '24h/24, 7j/7' : 'Horaires non définis';
        }

        $formatted = [];
        foreach (self::DAYS as $key => $label) {
            if (isset($this->schedule[$key])) {
                $day = $this->schedule[$key];
                if ($day['is_open']) {
                    $formatted[] = $label . ' : ' . $day['start'] . ' - ' . $day['end'];
                } else {
                    $formatted[] = $label . ' : Fermé';
                }
            }
        }

        return $formatted;
    }

    public function getDaySchedule($day) {
        if ($this->is_always_open) {
            return ['is_open' => true, 'start' => '00:00', 'end' => '23:59'];
        }

        return $this->schedule[$day] ?? ['is_open' => false, 'start' => null, 'end' => null];
    }
}