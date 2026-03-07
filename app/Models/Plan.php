<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'price',
        'duration_days',
        'features',
        'limitations',
        'max_services',
        'max_employees',
        'has_priority_support',
        'has_analytics',
        'has_api_access',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'features' => 'array',
        'limitations' => 'array',
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'max_services' => 'integer',
        'max_employees' => 'integer',
        'has_priority_support' => 'boolean',
        'has_analytics' => 'boolean',
        'has_api_access' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'features' => '[]',
        'limitations' => '[]',
        'is_active' => true,
        'sort_order' => 0
    ];

    // Relations
    public function entreprises()
    {
        return $this->hasMany(Entreprise::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // Accesseurs
    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 0, ',', ' ') . ' F CFA';
    }

    public function getDurationTextAttribute()
    {
        if ($this->duration_days < 30) {
            return $this->duration_days . ' jour' . ($this->duration_days > 1 ? 's' : '');
        } elseif ($this->duration_days == 30) {
            return '1 mois';
        } elseif ($this->duration_days == 90) {
            return '3 mois';
        } elseif ($this->duration_days == 180) {
            return '6 mois';
        } elseif ($this->duration_days == 365) {
            return '1 an';
        } else {
            return $this->duration_days . ' jours';
        }
    }

    public function getFeaturesListAttribute()
    {
        $features = $this->features ?? [];
        
        if ($this->max_services) {
            $features[] = "Jusqu'à {$this->max_services} services";
        } elseif ($this->max_services === null) {
            $features[] = "Services illimités";
        }
        
        if ($this->max_employees) {
            $features[] = "Jusqu'à {$this->max_employees} employés";
        }
        
        if ($this->has_priority_support) {
            $features[] = 'Support prioritaire';
        }
        
        if ($this->has_analytics) {
            $features[] = 'Statistiques avancées';
        }
        
        if ($this->has_api_access) {
            $features[] = 'Accès API';
        }

        return $features;
    }

    public function getLimitationsListAttribute()
    {
        $limitations = $this->limitations ?? [];
        
        if (!$this->has_priority_support) {
            $limitations[] = "Support standard uniquement";
        }
        
        if (!$this->has_analytics) {
            $limitations[] = "Pas de statistiques avancées";
        }
        
        if (!$this->has_api_access) {
            $limitations[] = "Pas d'accès API";
        }

        return $limitations;
    }

    // Mutators
    public function setFeaturesAttribute($value)
    {
        $this->attributes['features'] = json_encode(array_values(array_filter($value ?? [])));
    }

    public function setLimitationsAttribute($value)
    {
        $this->attributes['limitations'] = json_encode(array_values(array_filter($value ?? [])));
    }
}