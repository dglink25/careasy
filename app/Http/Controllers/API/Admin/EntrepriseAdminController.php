<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Plan;
use App\Models\Abonnement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Notifications\EntrepriseStatusChangedNotification;
use App\Notifications\TrialPeriodStartedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EntrepriseAdminController extends Controller{
    protected function ensureAdmin() {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Unauthorized. Admin only.');
        }
    }

    // Lister toutes les demandes (avec filtrage)
    public function index(Request $request){
        $this->ensureAdmin();

        $query = Entreprise::with(['prestataire', 'domaines', 'services']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('trial_status')) {
            if ($request->trial_status === 'in_trial') {
                $query->whereNotNull('trial_starts_at')
                      ->where('trial_ends_at', '>', now());
            } elseif ($request->trial_status === 'trial_expired') {
                $query->whereNotNull('trial_ends_at')
                      ->where('trial_ends_at', '<=', now());
            } elseif ($request->trial_status === 'trial_available') {
                $query->where('has_used_trial', false);
            }
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('pdg_full_name', 'like', "%{$q}%");
            });
        }

        $entreprises = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $entreprises]);
    }

    // Voir une entreprise avec TOUS les détails
    public function show($id) {
        $this->ensureAdmin();

        $entreprise = Entreprise::with(['prestataire', 'domaines', 'services', 'abonnements'])->find($id);
        
        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        // Ajouter les informations sur l'essai
        $entreprise->trial_info = $entreprise->trial_status;

        return response()->json($entreprise);
    }

    // Valider une entreprise avec activation automatique de l'essai gratuit
    public function approve(Request $request, $id) {
        $this->ensureAdmin();

        $entreprise = Entreprise::find($id);
        
        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        if ($entreprise->status === 'validated') {
            return response()->json(['message' => 'Entreprise déjà validée'], 400);
        }

        DB::beginTransaction();
        try {
            // Changer le statut
            $entreprise->status = 'validated';
            $entreprise->admin_note = $request->admin_note ?? null;
            
            // Activer la période d'essai si ce n'est pas déjà fait
            if (!$entreprise->has_used_trial) {
                $entreprise->activateTrialPeriod();
                
                // Créer un abonnement d'essai dans la table abonnements
                $this->createTrialSubscription($entreprise);
            }
            
            $entreprise->save();

            // Notifier le prestataire
            if ($entreprise->prestataire) {
                $entreprise->prestataire->notify(
                    new EntrepriseStatusChangedNotification($entreprise, 'validée', $entreprise->admin_note)
                );

                if ($entreprise->isInTrialPeriod()) {
                    $entreprise->prestataire->notify(
                        new TrialPeriodStartedNotification($entreprise)
                    );
                }   
                
            }

            DB::commit();

            return response()->json([
                'message' => 'Entreprise validée avec succès. Période d\'essai de 30 jours activée.',
                'entreprise' => $entreprise->load('abonnements'),
                'trial_info' => [
                    'starts_at' => $entreprise->trial_starts_at,
                    'ends_at' => $entreprise->trial_ends_at,
                    'days_remaining' => $entreprise->trial_days_remaining,
                    'max_services' => $entreprise->max_services_allowed,
                    'max_employees' => $entreprise->max_employees_allowed
                ]
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur validation entreprise: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la validation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function createTrialSubscription(Entreprise $entreprise) {
        // Vérifier s'il existe déjà un abonnement d'essai
        $existingTrial = Abonnement::where('entreprise_id', $entreprise->id)
            ->where('type', 'trial')
            ->first();

        if ($existingTrial) {
            return $existingTrial;
        }

        // Récupérer le plan d'essai ou créer les données par défaut
        $trialPlan = Plan::where('code', 'TRIAL')->first();
        
        $abonnement = new Abonnement();
        $abonnement->reference = Abonnement::genererReference();
        $abonnement->user_id = $entreprise->prestataire_id;
        $abonnement->entreprise_id = $entreprise->id;
        $abonnement->type = 'trial';
        $abonnement->plan_id = $trialPlan ? $trialPlan->id : null;
        $abonnement->date_debut = $entreprise->trial_starts_at;
        $abonnement->date_fin = $entreprise->trial_ends_at;
        $abonnement->statut = 'actif';
        $abonnement->renouvellement_auto = false;
        $abonnement->metadata = [
            'type' => 'essai_gratuit',
            'max_services' => $entreprise->max_services_allowed,
            'max_employees' => $entreprise->max_employees_allowed,
            'has_api_access' => $entreprise->has_api_access
        ];
        
        $abonnement->save();

        return $abonnement;
    }

    public function reject(Request $request, $id) {
        $this->ensureAdmin();

        $request->validate([
            'admin_note' => 'required|string|min:10'
        ]);

        $entreprise = Entreprise::find($id);
        
        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        if ($entreprise->status === 'rejected') {
            return response()->json(['message' => 'Entreprise déjà rejetée'], 400);
        }

        DB::beginTransaction();
        try {
            $entreprise->status = 'rejected';
            $entreprise->admin_note = $request->admin_note;
            $entreprise->save();

            // Notifier le prestataire
            if ($entreprise->prestataire) {
                $entreprise->prestataire->notify(
                    new EntrepriseStatusChangedNotification($entreprise, 'rejetée', $entreprise->admin_note)
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Entreprise rejetée',
                'entreprise' => $entreprise
            ], 200);
            
        } 
        catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur rejet entreprise: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors du rejet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function extendTrial(Request $request, $id) {
        $this->ensureAdmin();

        $request->validate([
            'days' => 'required|integer|min:1|max:90',
            'reason' => 'nullable|string'
        ]);

        $entreprise = Entreprise::find($id);
        
        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        if (!$entreprise->has_used_trial) {
            return response()->json(['message' => 'Cette entreprise n\'a pas encore utilisé sa période d\'essai'], 400);
        }

        DB::beginTransaction();
        try {
            // Prolonger la période d'essai
            if ($entreprise->isInTrialPeriod()) {
                // Si l'essai est en cours, ajouter des jours
                $nouvelleDateFin = $entreprise->trial_ends_at->addDays($request->days);
            } else {
                // Si l'essai est expiré, créer une nouvelle période
                $entreprise->trial_starts_at = now();
                $nouvelleDateFin = now()->addDays($request->days);
            }

            $entreprise->trial_ends_at = $nouvelleDateFin;
            $entreprise->save();

            $abonnement = Abonnement::where('entreprise_id', $entreprise->id)
                ->where('type', 'trial')
                ->where('statut', 'actif')
                ->first();

            if ($abonnement) {
                $abonnement->date_fin = $nouvelleDateFin;
                $abonnement->metadata = array_merge($abonnement->metadata ?? [], [
                    'extended_at' => now(),
                    'extended_by' => Auth::id(),
                    'extended_days' => $request->days,
                    'extended_reason' => $request->reason
                ]);
                $abonnement->save();
            }

            DB::commit();

            return response()->json([
                'message' => "Période d'essai prolongée de {$request->days} jours avec succès",
                'entreprise' => $entreprise,
                'new_trial_end' => $entreprise->trial_ends_at
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur prolongation essai: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la prolongation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}