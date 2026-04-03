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
use App\Http\Controllers\API\PushNotificationController;

class EntrepriseAdminController extends Controller
{
    protected function ensureAdmin()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Unauthorized. Admin only.');
        }
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $query = Entreprise::with(['prestataire', 'domaines', 'services']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('trial_status')) {
            if ($request->trial_status === 'in_trial') {
                $query->whereNotNull('trial_starts_at')->where('trial_ends_at', '>', now());
            } elseif ($request->trial_status === 'trial_expired') {
                $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<=', now());
            } elseif ($request->trial_status === 'trial_available') {
                $query->where('has_used_trial', false);
            }
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('pdg_full_name', 'like', "%{$q}%");
            });
        }

        return response()->json(['data' => $query->orderBy('created_at', 'desc')->get()]);
    }

    public function show($id)
    {
        $this->ensureAdmin();

        $entreprise = Entreprise::with(['prestataire', 'domaines', 'services', 'abonnements'])->find($id);

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        $entreprise->trial_info = $entreprise->trial_status;

        return response()->json($entreprise);
    }

    // ════════════════════════════════════════════════════════════════════
    //  APPROVE — sans DB::beginTransaction() (incompatible Neon pgBouncer)
    //  Chaque opération est exécutée séquentiellement.
    //  En cas d'erreur partielle, un log est émis pour correction manuelle.
    // ════════════════════════════════════════════════════════════════════
    public function approve(Request $request, $id)
    {
        $this->ensureAdmin();

        $entreprise = Entreprise::find($id);

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        if ($entreprise->status === 'validated') {
            return response()->json(['message' => 'Entreprise déjà validée'], 400);
        }

        try {
            // 1. Changer le statut
            $entreprise->status     = 'validated';
            $entreprise->admin_note = $request->admin_note ?? null;
            $entreprise->save();

            // 2. Changer le rôle du prestataire
            if ($entreprise->prestataire) {
                $entreprise->prestataire->update(['role' => 'prestataire']);
            }

            // 3. Activer la période d'essai
            if (!$entreprise->has_used_trial) {
                $entreprise->activateTrialPeriod();
                $this->createTrialSubscription($entreprise);
            }

            // 4. Notifications (non bloquantes)
            if ($entreprise->prestataire) {
                try {
                    $entreprise->prestataire->notify(
                        new EntrepriseStatusChangedNotification($entreprise, 'validée', $entreprise->admin_note)
                    );
                } catch (\Exception $e) {
                    Log::warning('Notification email approve échouée', ['error' => $e->getMessage()]);
                }

                if ($entreprise->isInTrialPeriod()) {
                    try {
                        $entreprise->prestataire->notify(new TrialPeriodStartedNotification($entreprise));
                    } catch (\Exception $e) {
                        Log::warning('Notification trial échouée', ['error' => $e->getMessage()]);
                    }
                }

                try {
                    PushNotificationController::sendToUser($entreprise->prestataire, [
                        'title' => '🎉 Entreprise validée !',
                        'body'  => "Votre entreprise \"{$entreprise->name}\" a été approuvée et est maintenant en ligne !",
                        'type'  => 'entreprise_approved',
                        'url'   => '/mes-entreprises',
                        'icon'  => '/logo192.png',
                        'badge' => '/badge.png',
                        'data'  => [
                            'entreprise_id'   => $entreprise->id,
                            'entreprise_name' => $entreprise->name,
                            'status'          => 'approved',
                        ],
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Notification push approve échouée', ['error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'message'    => 'Entreprise validée avec succès. Période d\'essai de 30 jours activée.',
                'entreprise' => $entreprise->load('abonnements'),
                'trial_info' => [
                    'starts_at'    => $entreprise->trial_starts_at,
                    'ends_at'      => $entreprise->trial_ends_at,
                    'days_remaining' => $entreprise->trial_days_remaining,
                    'max_services' => $entreprise->max_services_allowed,
                    'max_employees'=> $entreprise->max_employees_allowed,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur validation entreprise: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la validation',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function createTrialSubscription(Entreprise $entreprise)
    {
        $existing = Abonnement::where('entreprise_id', $entreprise->id)
            ->where('type', 'trial')
            ->first();

        if ($existing) {
            return $existing;
        }

        $trialPlan = Plan::where('code', 'TRIAL')->first();

        $abonnement = new Abonnement();
        $abonnement->reference          = Abonnement::genererReference();
        $abonnement->user_id            = $entreprise->prestataire_id;
        $abonnement->entreprise_id      = $entreprise->id;
        $abonnement->type               = 'trial';
        $abonnement->plan_id            = $trialPlan ? $trialPlan->id : null;
        $abonnement->date_debut         = $entreprise->trial_starts_at;
        $abonnement->date_fin           = $entreprise->trial_ends_at;
        $abonnement->statut             = 'actif';
        $abonnement->renouvellement_auto = false;
        $abonnement->metadata           = [
            'type'           => 'essai_gratuit',
            'max_services'   => $entreprise->max_services_allowed,
            'max_employees'  => $entreprise->max_employees_allowed,
            'has_api_access' => $entreprise->has_api_access,
        ];
        $abonnement->save();

        return $abonnement;
    }

    public function reject(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate(['admin_note' => 'required|string|min:10']);

        $entreprise = Entreprise::find($id);

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        if ($entreprise->status === 'rejected') {
            return response()->json(['message' => 'Entreprise déjà rejetée'], 400);
        }

        try {
            $entreprise->status     = 'rejected';
            $entreprise->admin_note = $request->admin_note;
            $entreprise->save();

            if ($entreprise->prestataire) {
                try {
                    $entreprise->prestataire->notify(
                        new EntrepriseStatusChangedNotification($entreprise, 'rejetée', $entreprise->admin_note)
                    );
                } catch (\Exception $e) {
                    Log::warning('Notification email reject échouée', ['error' => $e->getMessage()]);
                }

                try {
                    PushNotificationController::sendToUser($entreprise->prestataire, [
                        'title' => '⚠️ Entreprise refusée',
                        'body'  => "Votre entreprise \"{$entreprise->name}\" a été refusée. Motif: "
                                   . substr($request->admin_note, 0, 100)
                                   . (strlen($request->admin_note) > 100 ? '...' : ''),
                        'type'  => 'entreprise_rejected',
                        'url'   => '/mes-entreprises',
                        'icon'  => '/logo192.png',
                        'badge' => '/badge.png',
                        'data'  => [
                            'entreprise_id'   => $entreprise->id,
                            'entreprise_name' => $entreprise->name,
                            'status'          => 'rejected',
                            'admin_note'      => $request->admin_note,
                        ],
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Notification push reject échouée', ['error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'message'    => 'Entreprise rejetée',
                'entreprise' => $entreprise,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur rejet entreprise: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors du rejet',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function extendTrial(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'days'   => 'required|integer|min:1|max:90',
            'reason' => 'nullable|string',
        ]);

        $entreprise = Entreprise::find($id);

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        if (!$entreprise->has_used_trial) {
            return response()->json(['message' => 'Cette entreprise n\'a pas encore utilisé sa période d\'essai'], 400);
        }

        try {
            if ($entreprise->isInTrialPeriod()) {
                $nouvelleDateFin = $entreprise->trial_ends_at->addDays($request->days);
            } else {
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
                    'extended_at'     => now(),
                    'extended_by'     => Auth::id(),
                    'extended_days'   => $request->days,
                    'extended_reason' => $request->reason,
                ]);
                $abonnement->save();
            }

            if ($entreprise->prestataire) {
                try {
                    PushNotificationController::sendToUser($entreprise->prestataire, [
                        'title' => '📅 Période d\'essai prolongée',
                        'body'  => "Votre période d'essai pour \"{$entreprise->name}\" a été prolongée de {$request->days} jours.",
                        'type'  => 'trial_extended',
                        'url'   => '/abonnements',
                        'icon'  => '/logo192.png',
                        'data'  => [
                            'entreprise_id' => $entreprise->id,
                            'new_end_date'  => $nouvelleDateFin->format('Y-m-d'),
                        ],
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Notification push extend trial échouée', ['error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'message'       => "Période d'essai prolongée de {$request->days} jours avec succès",
                'entreprise'    => $entreprise,
                'new_trial_end' => $entreprise->trial_ends_at,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur prolongation essai: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la prolongation',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}