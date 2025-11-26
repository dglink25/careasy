<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    /**
     * Services de l’entreprise du prestataire
     */
    public function mine(){
        $user = Auth::user();

        $services = Service::with('entreprise', 'domaine')
            ->where('prestataire_id', $user->id)
            ->get();

        return response()->json($services);
    }

    /**
     * Services publics (tous)
     */
    public function index(){
        return Service::with('entreprise', 'domaine')
            ->whereHas('entreprise', fn ($q) => $q->where('status', 'validated'))
            ->get();
    }

    /**
     * Création service
     */
    public function store(Request $request){
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'entreprise_id' => 'required|exists:entreprises,id',
            'domaine_id'    => 'required|exists:domaines,id',
            'name'          => 'required|string|max:255',
            'price'         => 'nullable|numeric',
            'descriptions'  => 'nullable|string',
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
            return response()->json(['message'=>'Domaine non autorisé'], 403);
        }

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
            'medias'        => $medias,
        ]);

        return response()->json([
            'message'=>'Service créé',
            'service'=>$service
        ], 201);
    }
}
