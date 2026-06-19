<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AbonnementController extends Controller
{
    /**
     * Liste de tous les abonnements de l'utilisateur connecté.
     * Priorité : abonnements payants actifs > essais > expirés/annulés
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $abonnements = Abonnement::with(['plan', 'paiement', 'entreprise'])
            ->where('user_id', $user->id)
            ->orderByRaw("CASE WHEN statut = 'actif' AND date_fin > NOW() THEN 0 ELSE 1 END")
            ->orderBy('date_fin', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $abonnements->map(fn ($a) => $this->formatAbonnement($a)),
        ]);
    }

    /**
     * Détails d'un abonnement spécifique.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $abonnement = Abonnement::with(['plan', 'paiement', 'entreprise'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$abonnement) {
            return response()->json(['success' => false, 'message' => 'Abonnement non trouvé'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatAbonnement($abonnement),
        ]);
    }

    public function actif(Request $request)  {
        $user = $request->user();

        // 1. Chercher un abonnement PAYANT actif (type != 'trial')
        $abonnement = Abonnement::with(['plan'])
            ->where('user_id', $user->id)
            ->where('statut', 'actif')
            ->where('date_fin', '>', now())
            ->where(function ($q) {
                $q->where('type', '!=', 'trial')
                  ->orWhereNull('type');
            })
            ->orderBy('date_fin', 'desc')
            ->first();

        // 2. Si aucun abonnement payant, chercher un essai actif
        if (!$abonnement) {
            $abonnement = Abonnement::with(['plan'])
                ->where('user_id', $user->id)
                ->where('statut', 'actif')
                ->where('date_fin', '>', now())
                ->where('type', 'trial')
                ->orderBy('date_fin', 'desc')
                ->first();
        }

        if (!$abonnement) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun abonnement actif',
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'            => $abonnement->id,
                'reference'     => $abonnement->reference,
                'type'          => $abonnement->type ?? 'standard',
                'est_essai'     => $abonnement->type === 'trial',
                'plan'          => [
                    'id'   => $abonnement->plan->id,
                    'name' => $abonnement->plan->name,
                    'code' => $abonnement->plan->code,
                ],
                'date_fin'      => $abonnement->date_fin->format('d/m/Y'),
                'jours_restants'=> $abonnement->jours_restants,
                'entreprise_id' => $abonnement->entreprise_id,
            ],
        ]);
    }

    /**
     * Annuler un abonnement actif.
     * Route : POST /api/abonnements/{id}/annuler
     */
    public function annuler(Request $request, $id)
    {
        $user = $request->user();

        $abonnement = Abonnement::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$abonnement) {
            return response()->json(['success' => false, 'message' => 'Abonnement non trouvé'], 404);
        }

        if (!$abonnement->estActif()) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les abonnements actifs peuvent être annulés',
            ], 400);
        }

        if ($abonnement->type === 'trial') {
            return response()->json([
                'success' => false,
                'message' => "Les périodes d'essai ne peuvent pas être annulées",
            ], 400);
        }

        try {
            $abonnement->statut              = 'annule';
            $abonnement->date_annulation     = now();
            $abonnement->motif_annulation    = $request->reason ?? null;
            $abonnement->renouvellement_auto = false;
            $abonnement->save();

            return response()->json([
                'success' => true,
                'message' => 'Abonnement annulé avec succès',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur annulation abonnement:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => "Erreur lors de l'annulation",
            ], 500);
        }
    }

    // ─── Méthode cancel conservée pour rétrocompatibilité ──────────────────
    public function cancel(Request $request, $id)
    {
        return $this->annuler($request, $id);
    }

    // ─── Formatage uniforme d'un abonnement ────────────────────────────────
    private function formatAbonnement(Abonnement $abonnement): array
    {
        $isEssai = $abonnement->type === 'trial';

        $data = [
            'id'                 => $abonnement->id,
            'reference'          => $abonnement->reference,
            'type'               => $abonnement->type ?? 'standard',
            'est_essai'          => $isEssai,
            'entreprise_id'      => $abonnement->entreprise_id,
            'entreprise_name'    => $abonnement->entreprise?->name,
            'plan'               => $abonnement->plan ? [
                'id'            => $abonnement->plan->id,
                'name'          => $abonnement->plan->name,
                'code'          => $abonnement->plan->code,
                'description'   => $abonnement->plan->description,
                'duration_text' => $abonnement->plan->duration_text,
                'features_list' => $abonnement->plan->features_list,
                'max_services'  => $abonnement->plan->max_services,
                'max_employees' => $abonnement->plan->max_employees,
                'has_api_access'=> $abonnement->plan->has_api_access,
            ] : null,
            'date_debut'         => $abonnement->date_debut->format('d/m/Y'),
            'date_fin'           => $abonnement->date_fin->format('d/m/Y'),
            'date_fin_obj'       => $abonnement->date_fin->toIso8601String(), // pour le tri JS
            'statut'             => $abonnement->statut,
            'statut_libelle'     => $abonnement->statut_libelle,
            'statut_color'       => $abonnement->statut_color,
            'jours_restants'     => $abonnement->jours_restants,
            'est_actif'          => $abonnement->estActif(),
            'renouvellement_auto'=> $abonnement->renouvellement_auto,
            'montant'            => $isEssai ? 'Gratuit' : ($abonnement->paiement?->montant_formate),
            'montant_formate'    => $isEssai ? 'Gratuit' : ($abonnement->paiement?->montant_formate),
            'paiement'           => (!$isEssai && $abonnement->paiement) ? [
                'reference'   => $abonnement->paiement->reference,
                'montant'     => $abonnement->paiement->montant_formate,
                'montant_formate' => $abonnement->paiement->montant_formate,
                'methode'     => $abonnement->paiement->methode_paiement,
                'date'        => $abonnement->paiement->created_at->format('d/m/Y'),
                'facture_url' => $abonnement->paiement->facture_url,
            ] : null,
            'metadata'           => $abonnement->metadata,
            'created_at'         => $abonnement->created_at->format('d/m/Y'),
        ];

        return $data;
    }
}