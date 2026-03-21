<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RendezVous;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    // Ajouter une note
    public function store(Request $request, $rendezVousId) {
        $user = Auth::user();

        try {
            $rendezVous = RendezVous::findOrFail($rendezVousId);

            // Vérifier que l'utilisateur est bien le client
            if ($rendezVous->client_id !== $user->id) {
                return response()->json([
                    'message' => 'Vous n’êtes pas autorisé à noter ce service.'
                ], 403);
            }

            // Vérifier que le service est complété
            if ($rendezVous->status !== RendezVous::STATUS_COMPLETED) {
                return response()->json([
                    'message' => 'Le service doit être terminé avant de pouvoir le noter.'
                ], 400);
            }

            // Vérifier s'il a déjà noté
            if ($rendezVous->review) {
                return response()->json([
                    'message' => 'Vous avez déjà noté ce service.'
                ], 409);
            }

            $validated = $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500'
            ]);

            DB::beginTransaction();

            $review = Review::create([
                'rendez_vous_id' => $rendezVous->id,
                'client_id'      => $user->id,
                'prestataire_id' => $rendezVous->prestataire_id,
                'rating'         => $validated['rating'],
                'comment'        => $validated['comment'] ?? null
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Merci pour votre retour !',
                'review' => $review
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Une erreur est survenue lors de l’enregistrement de la note.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Optionnel : signaler un service
    public function report($rendezVousId) {
        $user = Auth::user();
        try {
            $rendezVous = RendezVous::findOrFail($rendezVousId);

            if ($rendezVous->client_id !== $user->id) {
                return response()->json([
                    'message' => 'Vous n’êtes pas autorisé à signaler ce service.'
                ], 403);
            }

            $review = $rendezVous->review;
            if (!$review) {
                return response()->json([
                    'message' => 'Vous devez d’abord noter le service avant de le signaler.'
                ], 400);
            }

            $review->reported = true;
            $review->save();

            return response()->json([
                'message' => 'Le service a été signalé avec succès.',
                'review' => $review
            ], 200);

        } 
        catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}