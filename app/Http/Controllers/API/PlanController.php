<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    /**
     * Afficher tous les plans actifs
     */
    public function index()
    {
        try {
            $plans = Plan::where('is_active', true)
                ->orderBy('sort_order')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $this->formatPlans($plans)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur index plans publics:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des plans'
            ], 500);
        }
    }

    public function show($id) {
        try {
            $plan = Plan::where('is_active', true)
                ->where('id', $id)
                ->first();
            
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan non trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatPlan($plan)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur show plan public:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du plan'
            ], 500);
        }
    }

    /**
     * Comparer tous les plans
     */
    public function compare() {
        try {
            $plans = Plan::where('is_active', true)
                ->orderBy('sort_order')
                ->get();
            
            $comparison = $plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'code' => $plan->code,
                    'price' => $plan->price,
                    'formatted_price' => number_format($plan->price, 0, ',', ' ') . ' F CFA',
                    'duration_days' => $plan->duration_days,
                    'duration' => $this->getDurationText($plan->duration_days),
                    'features' => $this->getFeaturesList($plan),
                    'limitations' => $this->getLimitationsList($plan),
                    'max_services' => $plan->max_services,
                    'max_services_text' => $plan->max_services ? "{$plan->max_services} services" : 'Illimité',
                    'max_employees' => $plan->max_employees,
                    'max_employees_text' => $plan->max_employees ? "{$plan->max_employees} employés" : 'Illimité',
                    'has_priority_support' => (bool) $plan->has_priority_support,
                    'has_analytics' => (bool) $plan->has_analytics,
                    'has_api_access' => (bool) $plan->has_api_access
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $comparison
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur compare plans:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la comparaison des plans'
            ], 500);
        }
    }

    /**
     * Formater une collection de plans
     */
    private function formatPlans($plans)
    {
        return $plans->map(function ($plan) {
            return $this->formatPlan($plan);
        });
    }

    /**
     * Formater un plan
     */
    private function formatPlan($plan)
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'code' => $plan->code,
            'description' => $plan->description,
            'price' => $plan->price,
            'formatted_price' => number_format($plan->price, 0, ',', ' ') . ' F CFA',
            'duration_days' => $plan->duration_days,
            'duration_text' => $this->getDurationText($plan->duration_days),
            'features' => $plan->features ?? [],
            'features_list' => $this->getFeaturesList($plan),
            'limitations' => $plan->limitations ?? [],
            'limitations_list' => $this->getLimitationsList($plan),
            'max_services' => $plan->max_services,
            'max_employees' => $plan->max_employees,
            'has_priority_support' => (bool) $plan->has_priority_support,
            'has_analytics' => (bool) $plan->has_analytics,
            'has_api_access' => (bool) $plan->has_api_access
        ];
    }

    /**
     * Obtenir le texte de durée
     */
    private function getDurationText($days)
    {
        if ($days < 30) {
            return $days . ' jour' . ($days > 1 ? 's' : '');
        } elseif ($days == 30) {
            return '1 mois';
        } elseif ($days == 90) {
            return '3 mois';
        } elseif ($days == 180) {
            return '6 mois';
        } elseif ($days == 365) {
            return '1 an';
        } else {
            $months = round($days / 30);
            return $months . ' mois';
        }
    }

    /**
     * Obtenir la liste des fonctionnalités
     */
    private function getFeaturesList($plan)
    {
        $features = $plan->features ?? [];
        
        if ($plan->max_services) {
            $features[] = "Jusqu'à {$plan->max_services} services";
        } elseif ($plan->max_services === null) {
            $features[] = "Services illimités";
        }
        
        if ($plan->max_employees) {
            $features[] = "Jusqu'à {$plan->max_employees} employés";
        }
        
        if ($plan->has_priority_support) {
            $features[] = "Support prioritaire";
        }
        
        if ($plan->has_analytics) {
            $features[] = "Statistiques avancées";
        }
        
        if ($plan->has_api_access) {
            $features[] = "Accès API";
        }
        
        return $features;
    }

    /**
     * Obtenir la liste des limitations
     */
    private function getLimitationsList($plan)
    {
        $limitations = $plan->limitations ?? [];
        
        if (!$plan->has_priority_support) {
            $limitations[] = "Support standard uniquement";
        }
        
        if (!$plan->has_analytics) {
            $limitations[] = "Pas de statistiques avancées";
        }
        
        if (!$plan->has_api_access) {
            $limitations[] = "Pas d'accès API";
        }
        
        return $limitations;
    }
}