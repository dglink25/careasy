<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Paiement;
use App\Models\Abonnement;
use App\Services\FedaPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaiementController extends Controller
{
    protected $fedapayService;
    protected $frontendUrl;

    public function __construct(FedaPayService $fedapayService)
    {
        $this->fedapayService = $fedapayService;
        $this->frontendUrl    = env('FRONTEND_URL', 'http://localhost:5173');
    }

    public function initierPaiement(Request $request, $planId)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'entreprise_id'    => 'nullable|exists:entreprises,id',
            'methode_paiement' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $plan = Plan::where('id', $planId)->where('is_active', true)->first();

        if (!$plan) {
            return response()->json(['success' => false, 'message' => 'Plan non trouvé ou inactif'], 404);
        }

        try {
            // Création du paiement (opération unique — pas de transaction)
            $paiement = Paiement::create([
                'reference'        => Paiement::genererReference(),
                'user_id'          => $user->id,
                'plan_id'          => $plan->id,
                'montant'          => $plan->price,
                'devise'           => 'XOF',
                'methode_paiement' => $request->methode_paiement,
                'statut'           => 'en_attente',
            ]);

            // Appel FedaPay (externe — hors de toute transaction DB)
            $fedapayResponse = $this->fedapayService->creerPaiement($paiement, $plan, $user);

            if (!$fedapayResponse['success']) {
                // On marque le paiement comme échoué plutôt que de le supprimer
                $paiement->update(['statut' => 'echec']);

                return response()->json([
                    'success' => false,
                    'message' => $fedapayResponse['message'] ?? 'Erreur lors de l\'initialisation du paiement',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Paiement initialisé avec succès',
                'data'    => [
                    'paiement' => [
                        'id'        => $paiement->id,
                        'reference' => $paiement->reference,
                        'montant'   => $paiement->montant_formate,
                        'statut'    => $paiement->statut,
                    ],
                    'plan' => [
                        'id'   => $plan->id,
                        'name' => $plan->name,
                        'code' => $plan->code,
                    ],
                    'payment_url'       => $fedapayResponse['payment_url'] ?? null,
                    'transaction_token' => $fedapayResponse['token'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur initiation paiement', [
                'error'   => $e->getMessage(),
                'user_id' => $user->id,
                'plan_id' => $planId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du paiement',
            ], 500);
        }
    }

    public function callback(Request $request)
    {
        Log::info('=== CALLBACK FEDAPAY REÇU ===', [
            'url'      => $request->fullUrl(),
            'method'   => $request->method(),
            'all_data' => $request->all(),
        ]);

        if ($request->isMethod('get')) {
            $transactionId = $request->query('id');
            $status        = $request->query('status');

            if ($transactionId) {
                $paiement = Paiement::where('fedapay_transaction_id', $transactionId)->first();

                if ($paiement) {
                    try {
                        $paiement->update([
                            'fedapay_status' => $status,
                            'statut'         => $status === 'approved' ? 'succes' : 'echec',
                            'date_paiement'  => $status === 'approved' ? now() : null,
                        ]);

                        if ($status === 'approved') {
                            $this->creerAbonnement($paiement);
                        }

                        return $status === 'approved'
                            ? redirect($this->frontendUrl . '/paiement/success?reference=' . $paiement->reference)
                            : redirect($this->frontendUrl . '/paiement/cancel?reference=' . $paiement->reference);

                    } catch (\Exception $e) {
                        Log::error('Erreur traitement callback GET', ['error' => $e->getMessage()]);
                    }
                }
            }
        }

        if ($request->isMethod('post')) {
            $data          = $request->all();
            $transactionId = $data['transaction']['id'] ?? $data['id'] ?? null;
            $status        = $data['transaction']['status'] ?? $data['status'] ?? null;
            $metadata      = $data['transaction']['metadata'] ?? $data['metadata'] ?? null;

            if ($transactionId && $metadata && isset($metadata['paiement_id'])) {
                $paiement = Paiement::find($metadata['paiement_id']);

                if ($paiement) {
                    try {
                        $paiement->update([
                            'fedapay_response'       => json_encode($data),
                            'fedapay_transaction_id' => $transactionId,
                            'fedapay_status'         => $status,
                            'statut'                 => $status === 'approved' ? 'succes' : 'echec',
                            'date_paiement'          => $status === 'approved' ? now() : null,
                        ]);

                        if ($status === 'approved') {
                            $this->creerAbonnement($paiement);
                        }

                        return response()->json(['success' => true]);

                    } catch (\Exception $e) {
                        Log::error('Erreur traitement callback POST', ['error' => $e->getMessage()]);
                        return response()->json(['error' => 'Erreur interne'], 500);
                    }
                }
            }
        }

        Log::warning('Callback non traité, redirection par défaut');
        return redirect($this->frontendUrl . '/plans');
    }

    public function success(Request $request)
    {
        return redirect($this->frontendUrl . '/paiement/success?reference=' . $request->get('reference'));
    }

    public function cancel(Request $request)
    {
        return redirect($this->frontendUrl . '/paiement/cancel?reference=' . $request->get('reference'));
    }

    public function verifierStatut(Request $request, $reference)
    {
        $paiement = Paiement::with(['plan', 'abonnement'])
            ->where('reference', $reference)
            ->first();

        if (!$paiement) {
            return response()->json(['success' => false, 'message' => 'Paiement non trouvé'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'paiement' => [
                    'id'             => $paiement->id,
                    'reference'      => $paiement->reference,
                    'statut'         => $paiement->statut,
                    'montant'        => $paiement->montant_formate,
                    'date_paiement'  => $paiement->date_paiement_formatee,
                ],
                'plan' => $paiement->plan ? [
                    'id'   => $paiement->plan->id,
                    'name' => $paiement->plan->name,
                    'code' => $paiement->plan->code,
                ] : null,
                'abonnement' => $paiement->abonnement ? [
                    'id'             => $paiement->abonnement->id,
                    'reference'      => $paiement->abonnement->reference,
                    'date_debut'     => $paiement->abonnement->date_debut->format('d/m/Y'),
                    'date_fin'       => $paiement->abonnement->date_fin->format('d/m/Y'),
                    'statut'         => $paiement->abonnement->statut_libelle,
                    'jours_restants' => $paiement->abonnement->jours_restants,
                ] : null,
            ],
        ]);
    }

    private function creerAbonnement(Paiement $paiement)
    {
        // Éviter les doublons si le callback est appelé deux fois
        $existing = Abonnement::where('paiement_id', $paiement->id)->first();
        if ($existing) {
            return $existing;
        }

        $plan = $paiement->plan;

        return Abonnement::create([
            'reference'          => Abonnement::genererReference(),
            'user_id'            => $paiement->user_id,
            'plan_id'            => $plan->id,
            'paiement_id'        => $paiement->id,
            'date_debut'         => now(),
            'date_fin'           => now()->addDays($plan->duration_days),
            'statut'             => 'actif',
            'renouvellement_auto'=> false,
        ]);
    }
}