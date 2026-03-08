<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Paiement;
use App\Models\Abonnement;
use App\Services\FedaPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaiementController extends Controller{
    protected $fedapayService;
    protected $frontendUrl;

    public function __construct(FedaPayService $fedapayService) {
        $this->fedapayService = $fedapayService;
        $this->frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
    }

    /**
     * Initier un paiement pour un plan
     */
    public function initierPaiement(Request $request, $planId)  {
        $user = $request->user();
        
        // Validation
        $validator = Validator::make($request->all(), [
            'entreprise_id' => 'nullable|exists:entreprises,id',
            'methode_paiement' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Récupérer le plan
        $plan = Plan::where('id', $planId)
            ->where('is_active', true)
            ->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan non trouvé ou inactif'
            ], 404);
        }

        // Vérifier si l'utilisateur a déjà un abonnement actif
        $abonnementActif = Abonnement::where('user_id', $user->id)
            ->where('statut', 'actif')
            ->where('date_fin', '>', now())
            ->first();

        if ($abonnementActif) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà un abonnement actif',
                'abonnement' => $abonnementActif
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Créer le paiement
            $paiement = Paiement::create([
                'reference' => Paiement::genererReference(),
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'montant' => $plan->price,
                'devise' => 'XOF',
                'methode_paiement' => $request->methode_paiement,
                'statut' => 'en_attente'
            ]);

            // Appeler FedaPay
            $fedapayResponse = $this->fedapayService->creerPaiement($paiement, $plan, $user);

            if (!$fedapayResponse['success']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $fedapayResponse['message'] ?? 'Erreur lors de l\'initialisation du paiement'
                ], 500);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement initialisé avec succès',
                'data' => [
                    'paiement' => [
                        'id' => $paiement->id,
                        'reference' => $paiement->reference,
                        'montant' => $paiement->montant_formate,
                        'statut' => $paiement->statut
                    ],
                    'plan' => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'code' => $plan->code
                    ],
                    'payment_url' => $fedapayResponse['payment_url'] ?? null,
                    'transaction_token' => $fedapayResponse['token'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur initiation paiement', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'plan_id' => $planId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du paiement'
            ], 500);
        }
    }

    public function callback(Request $request) {
        Log::info('=== CALLBACK FEDAPAY REÇU ===', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'all_data' => $request->all(),
            'query' => $request->query(),
            'content' => $request->getContent()
        ]);

        // Pour FedaPay en mode redirection (GET avec paramètres)
        if ($request->isMethod('get')) {
            $transactionId = $request->query('id');
            $status = $request->query('status');
            $metadataJson = $request->query('metadata', '{}');
            $metadata = json_decode($metadataJson, true);
            
            Log::info('Callback GET - données extraites', [
                'transaction_id' => $transactionId,
                'status' => $status,
                'metadata' => $metadata
            ]);

            if ($transactionId) {
                // Chercher le paiement par transaction_id dans fedapay_response
                $paiement = Paiement::where('fedapay_transaction_id', $transactionId)->first();
                
                if ($paiement) {
                    Log::info('Paiement trouvé via transaction_id', ['paiement_id' => $paiement->id]);
                    
                    // Mettre à jour le statut
                    DB::beginTransaction();
                    try {
                        $paiement->update([
                            'fedapay_status' => $status,
                            'statut' => $status === 'approved' ? 'succes' : 'echec',
                            'date_paiement' => $status === 'approved' ? now() : null
                        ]);

                        if ($status === 'approved') {
                            $this->creerAbonnement($paiement);
                        }

                        DB::commit();

                        // Rediriger vers le frontend
                        if ($status === 'approved') {
                            return redirect($this->frontendUrl . '/paiement/success?reference=' . $paiement->reference);
                        } else {
                            return redirect($this->frontendUrl . '/paiement/cancel?reference=' . $paiement->reference);
                        }
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Erreur traitement callback GET', ['error' => $e->getMessage()]);
                    }
                }
            }
        }

        // Pour FedaPay en mode webhook (POST)
        if ($request->isMethod('post')) {
            $data = $request->all();
            
            $transactionId = $data['transaction']['id'] ?? $data['id'] ?? null;
            $status = $data['transaction']['status'] ?? $data['status'] ?? null;
            $metadata = $data['transaction']['metadata'] ?? $data['metadata'] ?? null;

            Log::info('Callback POST - données extraites', [
                'transaction_id' => $transactionId,
                'status' => $status,
                'metadata' => $metadata
            ]);

            if ($transactionId && $metadata && isset($metadata['paiement_id'])) {
                $paiement = Paiement::find($metadata['paiement_id']);

                if ($paiement) {
                    DB::beginTransaction();
                    try {
                        $paiement->update([
                            'fedapay_response' => json_encode($data),
                            'fedapay_transaction_id' => $transactionId,
                            'fedapay_status' => $status,
                            'statut' => $status === 'approved' ? 'succes' : 'echec',
                            'date_paiement' => $status === 'approved' ? now() : null
                        ]);

                        if ($status === 'approved') {
                            $this->creerAbonnement($paiement);
                        }

                        DB::commit();

                        return response()->json(['success' => true]);
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Erreur traitement callback POST', ['error' => $e->getMessage()]);
                        return response()->json(['error' => 'Erreur interne'], 500);
                    }
                }
            }
        }

        // Si on arrive ici, rediriger vers le frontend par défaut
        Log::warning('Callback non traité, redirection par défaut');
        return redirect($this->frontendUrl . '/plans');
    }

    /**
     * Page de succès - Redirige vers le frontend
     */
    public function success(Request $request)
    {
        $reference = $request->get('reference');
        return redirect($this->frontendUrl . "/paiement/success?reference=" . $reference);
    }

    /**
     * Page d'annulation - Redirige vers le frontend
     */
    public function cancel(Request $request)
    {
        $reference = $request->get('reference');
        return redirect($this->frontendUrl . "/paiement/cancel?reference=" . $reference);
    }

    /**
     * Vérifier le statut d'un paiement
     */
    public function verifierStatut(Request $request, $reference)
    {
        $paiement = Paiement::with(['plan', 'abonnement'])
            ->where('reference', $reference)
            ->first();

        if (!$paiement) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'paiement' => [
                    'id' => $paiement->id,
                    'reference' => $paiement->reference,
                    'statut' => $paiement->statut,
                    'montant' => $paiement->montant_formate,
                    'date_paiement' => $paiement->date_paiement_formatee
                ],
                'plan' => $paiement->plan ? [
                    'id' => $paiement->plan->id,
                    'name' => $paiement->plan->name,
                    'code' => $paiement->plan->code
                ] : null,
                'abonnement' => $paiement->abonnement ? [
                    'id' => $paiement->abonnement->id,
                    'reference' => $paiement->abonnement->reference,
                    'date_debut' => $paiement->abonnement->date_debut->format('d/m/Y'),
                    'date_fin' => $paiement->abonnement->date_fin->format('d/m/Y'),
                    'statut' => $paiement->abonnement->statut_libelle,
                    'jours_restants' => $paiement->abonnement->jours_restants
                ] : null
            ]
        ]);
    }

    /**
     * Créer un abonnement
     */
    private function creerAbonnement(Paiement $paiement) {
        $plan = $paiement->plan;
        
        $dateDebut = now();
        $dateFin = now()->addDays($plan->duration_days);

        return Abonnement::create([
            'reference' => Abonnement::genererReference(),
            'user_id' => $paiement->user_id,
            'plan_id' => $plan->id,
            'paiement_id' => $paiement->id,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'statut' => 'actif',
            'renouvellement_auto' => false
        ]);
    }
}