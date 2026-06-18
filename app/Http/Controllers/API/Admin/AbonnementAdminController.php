<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AbonnementAdminController extends Controller{
    protected function ensureAdmin()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Unauthorized. Admin only.');
        }
    }

    /**
     * Liste tous les abonnements de tous les utilisateurs
     */
    public function index(Request $request) {
        $this->ensureAdmin();

        $query = Abonnement::with([
            'user:id,name,email,phone',
            'plan:id,name,code,description',
            'entreprise:id,name',
            'paiement'
        ]);

        // Filtre par statut
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        // Filtre par type (trial vs payant)
        if ($request->filled('type')) {
            if ($request->type === 'trial') {
                $query->where('type', 'trial');
            } elseif ($request->type === 'paid') {
                $query->where('type', '!=', 'trial');
            }
        }

        // Recherche texte
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($qb) use ($q) {
                $qb->where('reference', 'like', "%{$q}%")
                   ->orWhereHas('user', function ($sq) use ($q) {
                       $sq->where('name', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%");
                   })
                   ->orWhereHas('entreprise', function ($sq) use ($q) {
                       $sq->where('name', 'like', "%{$q}%");
                   });
            });
        }

        $abonnements = $query->orderBy('created_at', 'desc')->get();

        $data = $abonnements->map(function ($abonnement) {
            return [
                'id' => $abonnement->id,
                'reference' => $abonnement->reference,
                'type' => $abonnement->type ?? 'standard',
                'prestataire_name' => $abonnement->user?->name,
                'prestataire_email' => $abonnement->user?->email,
                'prestataire_phone' => $abonnement->user?->phone,
                'entreprise_id' => $abonnement->entreprise_id,
                'entreprise_name' => $abonnement->entreprise?->name,
                'plan' => $abonnement->plan ? [
                    'id' => $abonnement->plan->id,
                    'name' => $abonnement->plan->name,
                    'code' => $abonnement->plan->code,
                    'description' => $abonnement->plan->description,
                ] : null,
                'date_debut' => $abonnement->date_debut?->format('d/m/Y'),
                'date_fin' => $abonnement->date_fin?->format('d/m/Y'),
                'statut' => $abonnement->statut,
                'statut_libelle' => $abonnement->statut_libelle,
                'statut_color' => $abonnement->statut_color,
                'jours_restants' => $abonnement->jours_restants,
                'est_actif' => $abonnement->estActif(),
                'renouvellement_auto' => $abonnement->renouvellement_auto,
                'paiement' => $abonnement->paiement ? [
                    'reference' => $abonnement->paiement->reference,
                    'montant' => $abonnement->paiement->montant_formate,
                    'methode' => $abonnement->paiement->methode_paiement,
                    'date' => $abonnement->paiement->created_at->format('d/m/Y'),
                ] : null,
                'montant' => $abonnement->type === 'trial' ? 'Gratuit' : 
                            ($abonnement->paiement?->montant_formate ?? '—'),
                'metadata' => $abonnement->metadata,
                'created_at' => $abonnement->created_at->format('d/m/Y'),
            ];
        });

        // Statistiques
        $stats = [
            'total' => $abonnements->count(),
            'actifs' => $abonnements->where('statut', 'actif')->count(),
            'trial' => $abonnements->where('type', 'trial')->count(),
            'paid' => $abonnements->where('type', '!=', 'trial')->where('statut', 'actif')->count(),
            'expire' => $abonnements->whereIn('statut', ['expire', 'expiré'])->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
            'stats' => $stats,
            'total' => $data->count(),
        ]);
    }

    /**
     * Détails d'un abonnement spécifique
     */
    public function show(Request $request, $id) {
        $this->ensureAdmin();

        $abonnement = Abonnement::with([
            'user:id,name,email,phone',
            'plan',
            'entreprise',
            'paiement'
        ])->find($id);

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
                'type' => $abonnement->type ?? 'standard',
                'user' => [
                    'id' => $abonnement->user?->id,
                    'name' => $abonnement->user?->name,
                    'email' => $abonnement->user?->email,
                    'phone' => $abonnement->user?->phone,
                ],
                'entreprise' => $abonnement->entreprise ? [
                    'id' => $abonnement->entreprise->id,
                    'name' => $abonnement->entreprise->name,
                ] : null,
                'plan' => $abonnement->plan ? [
                    'id' => $abonnement->plan->id,
                    'name' => $abonnement->plan->name,
                    'code' => $abonnement->plan->code,
                    'description' => $abonnement->plan->description,
                ] : null,
                'date_debut' => $abonnement->date_debut?->format('d/m/Y H:i'),
                'date_fin' => $abonnement->date_fin?->format('d/m/Y H:i'),
                'statut' => $abonnement->statut,
                'statut_libelle' => $abonnement->statut_libelle,
                'jours_restants' => $abonnement->jours_restants,
                'est_actif' => $abonnement->estActif(),
                'renouvellement_auto' => $abonnement->renouvellement_auto,
                'paiement' => $abonnement->paiement ? [
                    'reference' => $abonnement->paiement->reference,
                    'montant' => $abonnement->paiement->montant_formate,
                    'methode' => $abonnement->paiement->methode_paiement,
                    'date' => $abonnement->paiement->date_paiement_formatee,
                    'facture_url' => $abonnement->paiement->facture_url,
                ] : null,
                'metadata' => $abonnement->metadata,
                'created_at' => $abonnement->created_at->format('d/m/Y H:i'),
            ]
        ]);
    }
}
