<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AbonnementController extends Controller
{
    
    public function index(Request $request){
        $user = $request->user();
        
        $abonnements = Abonnement::with(['plan', 'paiement', 'entreprise'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $abonnements->map(function ($abonnement) {
                $data = [
                    'id' => $abonnement->id,
                    'reference' => $abonnement->reference,
                    'type' => $abonnement->type ?? 'standard',
                    'entreprise_id' => $abonnement->entreprise_id,
                    'entreprise_name' => $abonnement->entreprise->name ?? null,
                    'plan' => [
                        'id' => $abonnement->plan->id,
                        'name' => $abonnement->plan->name,
                        'code' => $abonnement->plan->code,
                        'description' => $abonnement->plan->description,
                        'duration_text' => $abonnement->plan->duration_text,
                        'features_list' => $abonnement->plan->features_list,
                        'max_services' => $abonnement->plan->max_services,
                        'max_employees' => $abonnement->plan->max_employees,
                        'has_api_access' => $abonnement->plan->has_api_access
                    ],
                    'date_debut' => $abonnement->date_debut->format('d/m/Y'),
                    'date_fin' => $abonnement->date_fin->format('d/m/Y'),
                    'date_fin_obj' => $abonnement->date_fin,
                    'statut' => $abonnement->statut,
                    'statut_libelle' => $abonnement->statut_libelle,
                    'statut_color' => $abonnement->statut_color,
                    'jours_restants' => $abonnement->jours_restants,
                    'est_actif' => $abonnement->estActif(),
                    'renouvellement_auto' => $abonnement->renouvellement_auto,
                    'montant' => $abonnement->paiement ? $abonnement->paiement->montant_formate : null,
                    'montant_formate' => $abonnement->paiement ? $abonnement->paiement->montant_formate : null,
                    'paiement' => $abonnement->paiement ? [
                        'reference' => $abonnement->paiement->reference,
                        'montant' => $abonnement->paiement->montant_formate,
                        'montant_formate' => $abonnement->paiement->montant_formate,
                        'methode' => $abonnement->paiement->methode_paiement,
                        'date' => $abonnement->paiement->created_at->format('d/m/Y'),
                        'facture_url' => $abonnement->paiement->facture_url
                    ] : null,
                    'metadata' => $abonnement->metadata,
                    'created_at' => $abonnement->created_at->format('d/m/Y')
                ];

                // Ajouter des infos spécifiques pour les essais
                if ($abonnement->type === 'trial') {
                    $data['est_essai'] = true;
                    $data['montant'] = 'Gratuit';
                    $data['montant_formate'] = 'Gratuit';
                }

                return $data;
            })
        ]);
    }

    /**
     * Détails d'un abonnement
     */
    public function show(Request $request, $id) {
        $user = $request->user();
        
        $abonnement = Abonnement::with(['plan', 'paiement', 'entreprise'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$abonnement) {
            return response()->json([
                'success' => false,
                'message' => 'Abonnement non trouvé'
            ], 404);
        }

        $data = [
            'id' => $abonnement->id,
            'reference' => $abonnement->reference,
            'type' => $abonnement->type ?? 'standard',
            'entreprise' => $abonnement->entreprise ? [
                'id' => $abonnement->entreprise->id,
                'name' => $abonnement->entreprise->name
            ] : null,
            'plan' => [
                'id' => $abonnement->plan->id,
                'name' => $abonnement->plan->name,
                'code' => $abonnement->plan->code,
                'description' => $abonnement->plan->description,
                'duration_text' => $abonnement->plan->duration_text,
                'features_list' => $abonnement->plan->features_list,
                'max_services' => $abonnement->plan->max_services,
                'max_employees' => $abonnement->plan->max_employees,
                'has_api_access' => $abonnement->plan->has_api_access
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
                'date' => $abonnement->paiement->date_paiement_formatee,
                'facture_url' => $abonnement->paiement->facture_url
            ] : null,
            'metadata' => $abonnement->metadata,
            'created_at' => $abonnement->created_at->format('d/m/Y H:i')
        ];

        if ($abonnement->type === 'trial') {
            $data['est_essai'] = true;
            $data['montant'] = 'Gratuit';
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function actif(Request $request)  {
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
                'type' => $abonnement->type,
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


    public function cancel(Request $request, $id) {
        $user = $request->user();
        
        $abonnement = Abonnement::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$abonnement) {
            return response()->json([
                'success' => false,
                'message' => 'Abonnement non trouvé'
            ], 404);
        }

        if (!$abonnement->estActif()) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les abonnements actifs peuvent être annulés'
            ], 400);
        }

        if ($abonnement->type === 'trial') {
            return response()->json([
                'success' => false,
                'message' => 'Les périodes d\'essai ne peuvent pas être annulées'
            ], 400);
        }

        try {
            $abonnement->statut = 'annule';
            $abonnement->date_annulation = now();
            $abonnement->motif_annulation = $request->reason;
            $abonnement->save();

            // Désactiver le renouvellement auto
            $abonnement->renouvellement_auto = false;
            $abonnement->save();

            return response()->json([
                'success' => true,
                'message' => 'Abonnement annulé avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur annulation abonnement:', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation'
            ], 500);
        }
    }
}