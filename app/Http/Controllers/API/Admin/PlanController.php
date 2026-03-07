<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller{
    public function index() {
        try {
            $plans = Plan::orderBy('sort_order')->get();
            
            // Formater les données pour le frontend
            $plans->transform(function ($plan) {
                return $this->formatPlan($plan);
            });
            
            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur index plans:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des plans'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:plans,code',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'features.*' => 'string|max:255',
            'limitations' => 'nullable|array',
            'limitations.*' => 'string|max:255',
            'max_services' => 'nullable|integer|min:0',
            'max_employees' => 'nullable|integer|min:0',
            'has_priority_support' => 'boolean',
            'has_analytics' => 'boolean',
            'has_api_access' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->all();
            
            // S'assurer que features et limitations sont des tableaux
            if (!isset($data['features']) || !is_array($data['features'])) {
                $data['features'] = [];
            }
            if (!isset($data['limitations']) || !is_array($data['limitations'])) {
                $data['limitations'] = [];
            }
            
            // Nettoyer les valeurs vides
            $data['features'] = array_values(array_filter($data['features']));
            $data['limitations'] = array_values(array_filter($data['limitations']));
            
            $plan = Plan::create($data);
            
            Log::info('Plan créé', ['admin_id' => auth()->id(), 'plan_id' => $plan->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Plan créé avec succès',
                'data' => $this->formatPlan($plan)
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Erreur création plan:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du plan'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $plan = Plan::find($id);
            
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
            Log::error('Erreur show plan:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du plan'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $plan = Plan::find($id);
        
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:20|unique:plans,code,' . $id,
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'duration_days' => 'sometimes|integer|min:1',
            'features' => 'nullable|array',
            'features.*' => 'string|max:255',
            'limitations' => 'nullable|array',
            'limitations.*' => 'string|max:255',
            'max_services' => 'nullable|integer|min:0',
            'max_employees' => 'nullable|integer|min:0',
            'has_priority_support' => 'boolean',
            'has_analytics' => 'boolean',
            'has_api_access' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->all();
            
            // Gérer les tableaux
            if (isset($data['features']) && is_array($data['features'])) {
                $data['features'] = array_values(array_filter($data['features']));
            }
            if (isset($data['limitations']) && is_array($data['limitations'])) {
                $data['limitations'] = array_values(array_filter($data['limitations']));
            }
            
            $plan->update($data);
            
            Log::info('Plan mis à jour', ['admin_id' => auth()->id(), 'plan_id' => $plan->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Plan mis à jour avec succès',
                'data' => $this->formatPlan($plan->fresh())
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour plan:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du plan'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $plan = Plan::find($id);
        
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan non trouvé'
            ], 404);
        }

        // Vérifier si des entreprises utilisent ce plan
        if ($plan->entreprises()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce plan est utilisé par des entreprises et ne peut pas être supprimé'
            ], 422);
        }

        try {
            $plan->delete();
            
            Log::info('Plan supprimé', ['admin_id' => auth()->id(), 'plan_id' => $plan->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Plan supprimé avec succès'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur suppression plan:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du plan'
            ], 500);
        }
    }

    public function updateOrder(Request $request) {
        $validator = Validator::make($request->all(), [
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:plans,id',
            'orders.*.sort_order' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->orders as $order) {
                Plan::where('id', $order['id'])->update(['sort_order' => $order['sort_order']]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Ordre mis à jour avec succès'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour ordre:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'ordre'
            ], 500);
        }
    }

    public function toggleStatus($id)
    {
        $plan = Plan::find($id);
        
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan non trouvé'
            ], 404);
        }

        try {
            $plan->is_active = !$plan->is_active;
            $plan->save();
            
            $status = $plan->is_active ? 'activé' : 'désactivé';
            
            return response()->json([
                'success' => true,
                'message' => "Plan {$status} avec succès",
                'data' => ['is_active' => $plan->is_active]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur toggleStatus:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut'
            ], 500);
        }
    }

    /**
     * Formater un plan pour l'API
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
            'has_api_access' => (bool) $plan->has_api_access,
            'is_active' => (bool) $plan->is_active,
            'sort_order' => $plan->sort_order,
            'created_at' => $plan->created_at,
            'updated_at' => $plan->updated_at
        ];
    }

    /**
     * Obtenir le texte de durée formaté
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
     * Obtenir la liste complète des fonctionnalités
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
     * Obtenir la liste complète des limitations
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