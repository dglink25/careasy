<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Domaine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class EntrepriseController extends Controller{
    
    public function getFormData(){
        return response()->json([
            'domaines' => Domaine::orderBy('name')->get()
        ]);
    }

    //Liste toutes les entreprises validées (public)
    public function index(){
        return Entreprise::with('domaines', 'services')
            ->where('status', 'validated')
            ->get();
    }

    //Les entreprises du prestataire connecté
    public function mine(){
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $entreprises = Entreprise::with('domaines', 'services')
            ->where('prestataire_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($entreprises);
    }

    // Création d'une entreprise
    public function store(Request $request){
        Log::info('Données reçues:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'name'               => 'required|string|max:255',
            'domaine_ids'        => 'required|array',
            'domaine_ids.*'      => 'exists:domaines,id',
            'ifu_number'         => 'required|string',
            'ifu_file'           => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'rccm_number'        => 'required|string',
            'rccm_file'          => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'pdg_full_name'      => 'required|string',
            'pdg_full_profession'=> 'required|string',
            'role_user'          => 'required|string',
            'certificate_number' => 'required|string',
            'certificate_file'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'siege'              => 'nullable|string',
            'logo'               => 'nullable|image|max:2048',
            'image_boutique'     => 'nullable|image|max:2048',
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
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

            $data['latitude']  = $request->latitude;
            $data['longitude'] = $request->longitude;

            // Conversion Google Maps en adresse exacte
            $geo = Http::get("https://maps.googleapis.com/maps/api/geocode/json", [
                'latlng' => "{$request->latitude},{$request->longitude}",
                'key' => env('GOOGLE_MAPS_KEY')
            ]);

            if ($geo->successful() && isset($geo['results'][0])) {
                $data['google_formatted_address'] = $geo['results'][0]['formatted_address'];
            }

            // Upload des fichiers
            if ($request->hasFile('logo')) {
                $data['logo'] = $request->file('logo')->store('uploads/logos', 'public');
            }

            if ($request->hasFile('image_boutique')) {
                $data['image_boutique'] = $request->file('image_boutique')->store('uploads/boutiques', 'public');
            }

            if ($request->hasFile('ifu_file')) {
                $data['ifu_file'] = $request->file('ifu_file')->store('uploads/documents', 'public');
            }

            if ($request->hasFile('rccm_file')) {
                $data['rccm_file'] = $request->file('rccm_file')->store('uploads/documents', 'public');
            }

            if ($request->hasFile('certificate_file')) {
                $data['certificate_file'] = $request->file('certificate_file')->store('uploads/documents', 'public');
            }

            $entreprise = Entreprise::create($data);
            $entreprise->domaines()->sync($request->domaine_ids);
            
            // Recharger avec relations
            $entreprise->load('domaines', 'prestataire');

            DB::commit();

            return response()->json([
                'message' => 'Entreprise créée et envoyée en validation',
                'entreprise' => $entreprise
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création entreprise:', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Erreur interne',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Afficher une entreprise (public)
    public function show($id){
        $entreprise = Entreprise::with('domaines', 'services', 'prestataire')->find($id);

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        return response()->json($entreprise);
    }

    // Filtrer les entreprises par domaine
    public function indexByDomaine($domaineId){
        $entreprises = Entreprise::where('status', 'validated')
            ->whereHas('domaines', function ($q) use ($domaineId) {
                $q->where('domaines.id', $domaineId);
            })
            ->with('domaines', 'services')
            ->get();

        return response()->json($entreprises);
    }

    //Rechercher entreprise ou service
    public function search(Request $request){
        $s = $request->query('q');

        $entreprises = Entreprise::where('status', 'validated')
            ->where('name', 'LIKE', "%$s%")
            ->with('domaines', 'services')
            ->get();

        return response()->json($entreprises);
    }
    //Complèter profil entreprise
    public function completeProfile(Request $request, $id){
        $user = Auth::user();

        $entreprise = Entreprise::where('id', $id)
            ->where('prestataire_id', $user->id)
            ->first();

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise introuvable ou non autorisée'], 404);
        }

        if ($entreprise->status !== 'validated') {
            return response()->json(['message' => 'Action non autorisée : entreprise non validée'], 403);
        }

        $validator = Validator::make($request->all(), [
            'logo'           => 'nullable|image|max:2048',
            'siege'          => 'nullable|string|max:255',
            'whatsapp_phone' => 'nullable|string|max:25',
            'call_phone'     => 'nullable|string|max:25',
            'status_online'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            if ($request->hasFile('logo')) {
                // optionnel : Storage::disk('public')->delete($entreprise->logo);
                $entreprise->logo = $request->file('logo')->store('uploads/logos','public');
            }

            $entreprise->fill($request->only(['siege','whatsapp_phone','call_phone','status_online']));
            $entreprise->save();

            $entreprise->load('domaines','services');

            return response()->json([
                'message' => 'Profil entreprise mis à jour',
                'entreprise' => $entreprise
            ]);
        } 
        catch (\Exception $e) {
            Log::error('Erreur completeProfile:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

}