<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RendezVous;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    public function store(Request $request, $rendezVousId) {
        $user = Auth::user();

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            $rendezVous = RendezVous::where('id', $rendezVousId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($rendezVous->client_id !== $user->id) {
                DB::rollBack();
                return response()->json(['message' => 'Accès refusé'], 403);
            }

            if ($rendezVous->status !== RendezVous::STATUS_COMPLETED) {
                DB::rollBack();
                return response()->json(['message' => 'Service non terminé'], 400);
            }

            $existingReview = Review::where('rendez_vous_id', $rendezVous->id)
                ->where('client_id', $user->id)
                ->first();

            if ($existingReview) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Vous avez déjà noté ce service',
                    'review' => $existingReview
                ], 409);
            }

            $review = Review::create([
                'rendez_vous_id' => $rendezVous->id,
                'client_id'      => $user->id,
                'prestataire_id' => $rendezVous->prestataire_id,
                'rating'         => $validated['rating'],
                'comment'        => $validated['comment'] ?? null
            ]);

            DB::commit();
            
            $rendezVous->load('review');

            return response()->json([
                'message' => 'Merci pour votre retour',
                'review'  => $review,
                'rendez_vous' => $rendezVous
            ], 201);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            if ($e->getCode() == 23000) {
                return response()->json([
                    'message' => 'Vous avez déjà soumis une note pour ce service'
                ], 409);
            }

            Log::error('Erreur SQL Review:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur base de données'], 500);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur Review:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur interne serveur'], 500);
        }
    }

    public function report(Request $request, $rendezVousId) {
        $user = Auth::user();
        
        $validated = $request->validate([
            'reason' => 'required|string|max:100',
            'details' => 'nullable|string|max:500'
        ]);
        
        try {
            DB::beginTransaction();
            
            $rendezVous = RendezVous::with('review')
                ->lockForUpdate()
                ->findOrFail($rendezVousId);

            if ($rendezVous->client_id !== $user->id) {
                DB::rollBack();
                return response()->json(['message' => 'Accès refusé'], 403);
            }

            $review = $rendezVous->review;
            if (!$review) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Vous devez d\'abord noter le service avant de le signaler.'
                ], 400);
            }

            if ($review->reported) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Ce service a déjà été signalé.'
                ], 409);
            }
            
            $reportMessage = $validated['reason'];
            if (!empty($validated['details'])) {
                $reportMessage .= ': ' . $validated['details'];
            }
            
            $review->reported = true;
            $review->report_reason = $reportMessage;
            $review->reported_at = now();
            $review->save();

            DB::commit();
            
            $rendezVous->load('review');

            return response()->json([
                'message' => 'Le service a été signalé avec succès.',
                'review' => $review,
                'rendez_vous' => $rendezVous
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur signalement:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Une erreur est survenue.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}