<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ServiceController extends Controller{
    /**
     * Services de l'entreprise du prestataire
     */
    public function mine(){
        $user = Auth::user();

        $services = Service::with('entreprise', 'domaine')
            ->where('prestataire_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($services);
    }

    /**
     * Services publics (tous)
     */
    public function index(){
        return Service::with('entreprise', 'domaine')
            ->whereHas('entreprise', fn ($q) => $q->where('status', 'validated'))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Création service
     */
    public function store(Request $request){
        Log::info('Création service - Données reçues:', $request->all());
        
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'entreprise_id' => 'required|exists:entreprises,id',
            'domaine_id'    => 'required|exists:domaines,id',
            'name'          => 'required|string|max:255',
            'price'         => 'nullable|numeric',
            'descriptions'  => 'nullable|string',
            'start_time'    => 'nullable|date_format:H:i',
            'end_time'      => 'nullable|date_format:H:i',
            'is_open_24h'   => 'nullable|boolean',
            'medias.*'      => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors'=>$validator->errors()], 422);
        }

        $entreprise = Entreprise::where('id', $request->entreprise_id)
            ->where('prestataire_id', $user->id)
            ->first();

        if (!$entreprise) {
            return response()->json(['message'=>'Entreprise non autorisée'], 403);
        }

        if ($entreprise->status !== 'validated') {
            return response()->json(['message'=>'Entreprise non validée'], 403);
        }

        if (!$entreprise->domaines->pluck('id')->contains($request->domaine_id)) {
            return response()->json(['message'=>'Domaine non autorisé pour cette entreprise'], 403);
        }

        try {
            $medias = [];
            if ($request->hasFile('medias')) {
                foreach ($request->file('medias') as $file) {
                    $medias[] = $file->store('uploads/services', 'public');
                }
            }

            $service = Service::create([
                'entreprise_id' => $entreprise->id,
                'prestataire_id'=> $user->id,
                'domaine_id'    => $request->domaine_id,
                'name'          => $request->name,
                'price'         => $request->price,
                'descriptions'  => $request->descriptions,
                'start_time'    => $request->start_time,
                'end_time'      => $request->end_time,
                'is_open_24h'   => $request->is_open_24h ?? false,
                'medias'        => $medias,
            ]);

            // Recharger avec relations
            $service->load('entreprise', 'domaine');

            return response()->json([
                'message'=>'Service créé avec succès',
                'service'=>$service
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Erreur création service:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors de la création du service',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Afficher un service
     */
    public function show($id){
        $service = Service::with('entreprise', 'domaine')->find($id);

        if (!$service) {
            return response()->json(['message' => 'Service non trouvé'], 404);
        }

        return response()->json($service);
    }
}