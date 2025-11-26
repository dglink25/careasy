<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Domaine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class EntrepriseController extends Controller{
    // Retourne les données du formulaire
    
    public function getFormData(){
        return response()->json([
            'domaines' => Domaine::orderBy('name')->get()
        ]);
    }

    //Liste toutes les entreprises validées (public)
    public function index(){
        return Entreprise::with('domaines')
            ->where('status', 'validated')
            ->get();
    }

    //Les entreprises du prestataire connecté

    public function mine(){
        $user = Auth::user();

        return Entreprise::with('domaines', 'services')
            ->where('prestataire_id', $user->id)
            ->get();
    }

    // Création d’une entreprise
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'name'               => 'required|string|max:255',
            'domaine_ids'        => 'required|array',
            'domaine_ids.*'      => 'exists:domaines,id',
            'ifu_number'         => 'nullable|string',
            'rccm_number'        => 'nullable|string',
            'pdg_full_name'      => 'nullable|string',
            'pdg_full_profession'=> 'nullable|string',
            'siege'              => 'nullable|string',
            'certificate_number' => 'nullable|string',
            'logo'               => 'nullable|image|max:2048',
            'image_boutique'     => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $data = $request->except(['domaine_ids']);
            $data['prestataire_id'] = Auth::id();
            $data['status'] = 'pending';

            // Upload des images
            if ($request->hasFile('logo')) {
                $data['logo'] = $request->file('logo')->store('uploads/logos', 'public');
            }

            if ($request->hasFile('image_boutique')) {
                $data['image_boutique'] = $request->file('image_boutique')->store('uploads/boutiques', 'public');
            }

            $entreprise = Entreprise::create($data);
            $entreprise->domaines()->sync($request->domaine_ids);

            DB::commit();

            return response()->json([
                'message' => 'Entreprise créée et envoyée en validation',
                'entreprise' => $entreprise
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur interne',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Afficher une entreprise (public)

    public function show($id){
        $entreprise = Entreprise::with('domaines', 'services')->find($id);

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        return response()->json($entreprise);
    }

    // Filtrer les entreprises par domaine
    public function indexByDomaine($domaineId){
        $entreprises = Entreprise::where('status', 'validated')
            ->whereHas('domaines', function ($q) use ($domaineId) {
                $q->where('domaines.id', $domaineId);  // important !
            })
            ->with('domaines', 'services')
            ->get();

        return response()->json($entreprises);
    }


    //Rechercher entreprise ou service
    public function search(Request $request){
        $s = $request->query('q');

        $entreprises = Entreprise::where('name', 'LIKE', "%$s%")->get();

        return response()->json($entreprises);
    }
}
