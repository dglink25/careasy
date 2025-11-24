<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Entreprise;
use App\Models\Domaine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;


class ServiceController extends Controller{
    public function index(){
    
        $user = Auth::user();

        // Vérifier si l'utilisateur a une entreprise et qu'elle est validée
        if (!$user->entreprise || $user->entreprise->status !== 'valider') {
            return response()->json(['message' => 'Votre entreprise doit être validée pour accéder aux services.'], 403);
        }

        // Récupérer les services de l'entreprise
        $services = Service::with('entreprise', 'domaine')
            ->where('entreprise_id', $user->entreprise->id)
            ->get();

        return response()->json($services);

    }

    public function store(Request $request){
        $user = auth()->user(); 

        $validator = Validator::make($request->all(), [
            'entreprise_id' => 'required|exists:entreprises,id',
            'domaine_id' => 'required|exists:domaines,id',
            'name' => 'required|string|max:255',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'price' => 'nullable|numeric',
            'descriptions' => 'nullable|string',
            'medias' => 'nullable|array',
            'medias.*' => 'file|max:2048|mimes:jpg,jpeg,png,webp',
            'is_open_24h' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        $entreprise = Entreprise::where('id', $request->entreprise_id)
            ->where('prestataire_id', $user->id)
            ->first();

        if (!$entreprise) {
            return response()->json([
                'message' => "Action non autorisée : Impossible de créer un service dans cette entreprise, cette entreprise ne vous appartient pas."
            ], 403);
        }

        if ($entreprise->status !== 'validated') {
            return response()->json([
                'message' => "Votre entreprise n'est pas encore validée par l'administrateur.",
                'status' => $entreprise->status
            ], 403);
        }


        $domaineAutorise = $entreprise->domaines->pluck('id')->contains($request->domaine_id);

        if (!$domaineAutorise) {
            return response()->json([
                "message" => "Domaine non autorisé : ce domaine n'est pas associé à votre entreprise."
            ], 403);
        }

        // ----- UPLOAD MÉDIAS -----
        $medias = [];
        if ($request->hasFile('medias')) {
            foreach ($request->file('medias') as $file) {
                $medias[] = $file->store('uploads/services', 'public');
            }
        }

        // ----- CRÉATION DU SERVICE -----
        $service = Service::create([
            'entreprise_id' => $entreprise->id,
            'prestataire_id' => $user->id,
            'domaine_id' => $request->domaine_id,
            'name' => $request->name,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'price' => $request->price,
            'descriptions' => $request->descriptions,
            'medias' => $medias,
            'is_open_24h' => $request->is_open_24h ?? false,
        ]);

        return response()->json([
            'message' => 'Service créé avec succès',
            'service' => $service
        ], 201);
    }

    public function show($id){
        $service = Service::with('entreprise', 'domaine')->find($id);

        if (!$service) {
            return response()->json(['message' => 'Service non trouvé'], 404);
        }

        return response()->json($service);
    }
}
