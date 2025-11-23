<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Domaine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class EntrepriseController extends Controller{
    // Lister toutes les entreprises
    public function index(){
        $entreprises = Entreprise::with('domaines', 'services')->get();
        return response()->json($entreprises);
    }

    // Créer une nouvelle entreprise
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'id_prestataire' => 'required|exists:users,id',
            'domaine_ids' => 'required|array',
            'domaine_ids.*' => 'exists:domaines,id',
            'ifu_number' => 'nullable|string',
            'rccm_number' => 'nullable|string',
            'pdg_full_name' => 'nullable|string',
            'pdg_full_profession' => 'nullable|string',
            'role_user' => 'nullable|string',
            'siege' => 'nullable|string',
            'logo' => 'nullable|file|image|max:2048',
            'certificate_number' => 'nullable|string',
            'image_boutique' => 'nullable|file|image|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $entreprise = Entreprise::create($request->except('domaine_ids'));

            if ($request->has('domaine_ids')) {
                $entreprise->domaines()->sync($request->domaine_ids);
            }

            DB::commit();
            return response()->json(['message' => 'Entreprise créée avec succès', 'entreprise' => $entreprise], 201);
        } 
        catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la création', 'error' => $e->getMessage()], 500);
        }
    }

    // Afficher une entreprise
    public function show($id){
        $entreprise = Entreprise::with('domaines', 'services')->find($id);

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        return response()->json($entreprise);
    }
}
