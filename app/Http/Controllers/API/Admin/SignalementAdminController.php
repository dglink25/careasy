<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SignalementAdminController extends Controller
{
    protected function ensureAdmin()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Unauthorized. Admin only.');
        }
    }

    /**
     * Liste tous les avis signalés
     */
    public function index(Request $request)
    {
        $this->ensureAdmin();

        $query = Review::with([
            'client:id,name,email,phone',
            'prestataire:id,name,email',
            'rendezVous:id,service_id,date,start_time,end_time',
            'rendezVous.service:id,name',
        ])
        ->where('reported', true)
        ->orderBy('reported_at', 'desc');

        // Filtre par statut de résolution
        if ($request->filled('resolved')) {
            $query->where('resolved', $request->boolean('resolved'));
        }

        // Filtre par note
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filtre par note max
        if ($request->filled('max_rating')) {
            $query->where('rating', '<=', $request->max_rating);
        }

        // Recherche texte
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($qb) use ($q) {
                $qb->where('report_reason', 'like', "%{$q}%")
                   ->orWhere('comment', 'like', "%{$q}%")
                   ->orWhereHas('client', function ($sq) use ($q) {
                       $sq->where('name', 'like', "%{$q}%")
                          ->orWhere('email', 'like', "%{$q}%");
                   })
                   ->orWhereHas('prestataire', function ($sq) use ($q) {
                       $sq->where('name', 'like', "%{$q}%");
                   });
            });
        }

        $signalements = $query->get()->map(function ($review) {
            return [
                'id'            => $review->id,
                'rating'        => $review->rating,
                'comment'       => $review->comment,
                'report_reason' => $review->report_reason,
                'reported_at'   => $review->reported_at,
                'resolved'      => $review->resolved ?? false,
                'resolved_at'   => $review->resolved_at ?? null,
                'client'        => $review->client ? [
                    'id'    => $review->client->id,
                    'name'  => $review->client->name,
                    'email' => $review->client->email,
                    'phone' => $review->client->phone,
                ] : null,
                'prestataire'   => $review->prestataire ? [
                    'id'    => $review->prestataire->id,
                    'name'  => $review->prestataire->name,
                    'email' => $review->prestataire->email,
                ] : null,
                'rendez_vous'   => $review->rendezVous ? [
                    'id'         => $review->rendezVous->id,
                    'date'       => $review->rendezVous->date,
                    'start_time' => $review->rendezVous->start_time,
                    'end_time'   => $review->rendezVous->end_time,
                    'service'    => $review->rendezVous->service ? [
                        'id'   => $review->rendezVous->service->id,
                        'name' => $review->rendezVous->service->name,
                    ] : null,
                ] : null,
                'created_at' => $review->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $signalements,
            'total'   => $signalements->count(),
            'stats'   => [
                'total'    => Review::where('reported', true)->count(),
                'pending'  => Review::where('reported', true)->where('resolved', false)->count(),
                'resolved' => Review::where('reported', true)->where('resolved', true)->count(),
                'low_rating' => Review::where('reported', true)->where('rating', '<=', 2)->count(),
            ],
        ]);
    }

    /**
     * Marquer un signalement comme traité
     */
    public function resolve(Request $request, $id)
    {
        $this->ensureAdmin();

        $review = Review::where('reported', true)->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Signalement non trouvé',
            ], 404);
        }

        if ($review->resolved) {
            return response()->json([
                'success' => false,
                'message' => 'Ce signalement est déjà traité',
            ], 400);
        }

        try {
            $review->resolved    = true;
            $review->resolved_at = now();
            $review->save();

            Log::info('Signalement résolu', [
                'admin_id'  => Auth::id(),
                'review_id' => $review->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Signalement marqué comme traité',
                'data'    => ['id' => $review->id, 'resolved' => true, 'resolved_at' => $review->resolved_at],
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur résolution signalement:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la résolution',
            ], 500);
        }
    }

    /**
     * Supprimer un signalement (l'avis reste mais le flag reported est retiré)
     */
    public function dismiss(Request $request, $id)
    {
        $this->ensureAdmin();

        $review = Review::where('reported', true)->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Signalement non trouvé',
            ], 404);
        }

        try {
            $review->reported      = false;
            $review->report_reason = null;
            $review->reported_at   = null;
            $review->resolved      = false;
            $review->resolved_at   = null;
            $review->save();

            return response()->json([
                'success' => true,
                'message' => 'Signalement ignoré — l\'avis est restauré',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dismiss signalement:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'opération',
            ], 500);
        }
    }
}