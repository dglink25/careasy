<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AbonnementController extends Controller{
    
    public function index(Request $request){
        $user = $request->user();
        
        $abonnements = Abonnement::with(['plan', 'paiement'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $abonnements->map(function ($abonnement) {
                return [
                    'id' => $abonnement->id,
                    'reference' => $abonnement->reference,
                    'plan' => [
                        'id' => $abonnement->plan->id,
                        'name' => $abonnement->plan->name,
                        'code' => $abonnement->plan->code,
                        'duration_text' => $abonnement->plan->duration_text
                    ],
                    'date_debut' => $abonnement->date_debut->format('d/m/Y'),
                    'date_fin' => $abonnement->date_fin->format('d/m/Y'),
                    'statut' => $abonnement->statut_libelle,
                    'statut_color' => $abonnement->statut_color,
                    'jours_restants' => $abonnement->jours_restants,
                    'est_actif' => $abonnement->estActif(),
                    'montant' => $abonnement->paiement ? $abonnement->paiement->montant_formate : null,
                    'paiement_reference' => $abonnement->paiement ? $abonnement->paiement->reference : null
                ];
            })
        ]);
    }

    /**
     * Détails d'un abonnement
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        $abonnement = Abonnement::with(['plan', 'paiement'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$abonnement) {
            return response()->json([
                'success' => false,
                'message' => 'Abonnement non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $abonnement->id,
                'reference' => $abonnement->reference,
                'plan' => [
                    'id' => $abonnement->plan->id,
                    'name' => $abonnement->plan->name,
                    'code' => $abonnement->plan->code,
                    'description' => $abonnement->plan->description,
                    'duration_text' => $abonnement->plan->duration_text,
                    'features' => $abonnement->plan->features_list
                ],
                'date_debut' => $abonnement->date_debut->format('d/m/Y'),
                'date_fin' => $abonnement->date_fin->format('d/m/Y H:i'),
                'statut' => $abonnement->statut_libelle,
                'statut_color' => $abonnement->statut_color,
                'jours_restants' => $abonnement->jours_restants,
                'est_actif' => $abonnement->estActif(),
                'renouvellement_auto' => $abonnement->renouvellement_auto,
                'paiement' => $abonnement->paiement ? [
                    'reference' => $abonnement->paiement->reference,
                    'montant' => $abonnement->paiement->montant_formate,
                    'methode' => $abonnement->paiement->methode_paiement,
                    'date' => $abonnement->paiement->date_paiement_formatee
                ] : null,
                'created_at' => $abonnement->created_at->format('d/m/Y H:i')
            ]
        ]);
    }

    /**
     * Obtenir l'abonnement actif
     */
    public function actif(Request $request)
    {
        $user = $request->user();
        
        $abonnement = Abonnement::with(['plan'])
            ->where('user_id', $user->id)
            ->where('statut', 'actif')
            ->where('date_fin', '>', now())
            ->first();

        if (!$abonnement) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun abonnement actif'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $abonnement->id,
                'reference' => $abonnement->reference,
                'plan' => [
                    'id' => $abonnement->plan->id,
                    'name' => $abonnement->plan->name,
                    'code' => $abonnement->plan->code
                ],
                'date_fin' => $abonnement->date_fin->format('d/m/Y'),
                'jours_restants' => $abonnement->jours_restants
            ]
        ]);
    }
}