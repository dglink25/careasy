<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Domaine;
use App\Models\User;
use App\Notifications\NewEntrepriseCreatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

class EntrepriseController extends Controller
{
    public function __construct() {
       
    }
    
    public function getFormData(){
        return response()->json([
            'domaines' => Domaine::orderBy('name')->get()
        ]);
    }

    public function index(){
        return Entreprise::with('domaines', 'service')
            ->where('status', 'validated')
            ->get();
    }

    public function mine(){
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $entreprises = Entreprise::with('domaines', 'services')
            ->where('prestataire_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $entreprises->each(function ($e) {
            $e->append([
                'is_in_trial_period',
                'trial_days_remaining',
                'trial_status',
            ]);
        });

        return response()->json($entreprises);
    }
   

    public function show($id){
        $entreprise = Entreprise::with('domaines', 'services', 'prestataire')->find($id);

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée'], 404);
        }

        return response()->json($entreprise);
    }

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

    public function store(Request $request){
        Log::info('Données reçues:', $request->all());

         $user = Auth::user();

        // ── Vérifications métier ───────────────────────────────────────────────
        $existantes = Entreprise::where('prestataire_id', $user->id)->get();

        foreach ($existantes as $e) {
            // 1. Demande en attente → bloquer
            if ($e->status === 'pending') {
                return response()->json([
                    'message'         => 'Une demande est déjà en cours de traitement. Veuillez patienter.',
                    'status'          => 'pending',
                    'entreprise_name' => $e->name,
                ], 409);
            }

            // 2. Entreprise validée → essai ou expirée → abonnement payant requis
            if ($e->status === 'validated') {
                $abonnementPayant = \App\Models\Abonnement::where('user_id', $user->id)
                    ->where('type', '!=', 'trial')
                    ->where('statut', 'actif')
                    ->where('date_fin', '>', now())
                    ->first();

                if (!$abonnementPayant) {
                    $isInTrial    = $e->isInTrialPeriod();
                    $joursRestants = $e->trial_days_remaining;

                    return response()->json([
                        'message'         => $isInTrial
                            ? "Votre entreprise \"{$e->name}\" est en période d'essai ({$joursRestants} jours restants). Souscrivez un abonnement payant pour créer une nouvelle entreprise."
                            : "Votre période d'essai pour \"{$e->name}\" est terminée. Souscrivez un abonnement payant pour continuer.",
                        'status'          => 'validated',
                        'trial_status'    => $isInTrial ? 'in_trial' : 'expired',
                        'days_remaining'  => $joursRestants,
                        'entreprise_name' => $e->name,
                    ], 403);
                }
            }
            // 3. rejected → autorisé à resoumettre → on continue
        }
        
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
            'whatsapp_phone'    => 'required|string',
            'call_phone'        => 'required|string',
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
                $data['logo'] = $this->uploadToCloudinary($request->file('logo'), 'logos');
            }

            if ($request->hasFile('image_boutique')) {
                $data['image_boutique'] = $this->uploadToCloudinary($request->file('image_boutique'), 'boutiques');
            }

            if ($request->hasFile('ifu_file')) {
                $data['ifu_file'] = $this->uploadToCloudinary($request->file('ifu_file'), 'documents', 'ifu');
            }

            if ($request->hasFile('rccm_file')) {
                $data['rccm_file'] = $this->uploadToCloudinary($request->file('rccm_file'), 'documents', 'rccm');
            }

            if ($request->hasFile('certificate_file')) {
                $data['certificate_file'] = $this->uploadToCloudinary($request->file('certificate_file'), 'documents', 'certificates');
            }

            $entreprise = Entreprise::create($data);
            $entreprise->domaines()->sync($request->domaine_ids);

            try {
                $admins = User::where('role', 'admin')->get();
                
                foreach ($admins as $admin) {
                    $admin->notify(new NewEntrepriseCreatedNotification($entreprise, $request->user()));
                }

                foreach ($admins as $admin) {
                    event(new \App\Events\EntreprisePendingEvent($entreprise, $admin->id));
                }

                Log::info('Notifications envoyées aux admins pour la nouvelle entreprise', [
                    'entreprise_id' => $entreprise->id,
                    'admins_notified' => $admins->count()
                ]);

            } catch (\Exception $e) {
                Log::error('Erreur lors de l\'envoi des notifications aux admins', [
                    'error' => $e->getMessage(),
                    'entreprise_id' => $entreprise->id
                ]);
            }
           

            $entreprise->load('domaines', 'prestataire');

            DB::commit();

            return response()->json([
                'message' => 'Entreprise créée et envoyée en validation',
                'entreprise' => $entreprise
            ], 201);

        } 
        catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création entreprise:', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Erreur interne: '.$e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function uploadToCloudinary($file, $folder, $subfolder = null){
        Configuration::instance([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME', 'dsumeoiga'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
            'url' => [
                'secure' => true
            ]
        ]);

        $folderPath = $subfolder
            ? "entreprises/{$folder}/{$subfolder}"
            : "entreprises/{$folder}";

        $result = (new UploadApi())->upload(
            $file->getRealPath(),
            [
                'folder' => $folderPath,
                'resource_type' => 'auto',
            ]
        );

        return $result['secure_url'];
    }

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
                $entreprise->logo = $this->uploadToCloudinary($request->file('logo'), 'logos');
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

    public function update(Request $request, $id){
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Non authentifié',
                'status' => 'error'
            ], 401);
        }

        $entreprise = Entreprise::where('id', $id)
            ->where('prestataire_id', $user->id)
            ->first();

        if (!$entreprise) {
            return response()->json([
                'message' => 'Entreprise non trouvée ou vous n\'avez pas les permissions nécessaires',
                'status' => 'error'
            ], 404);
        }

        if ($entreprise->status !== 'validated') {
            return response()->json([
                'message' => 'Seules les entreprises validées peuvent être modifiées',
                'status' => 'error',
                'current_status' => $entreprise->status
            ], 403);
        }

        // Définir les règles de validation pour les champs modifiables
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'domaine_ids' => 'nullable|array',
            'domaine_ids.*' => 'exists:domaines,id',
            'siege' => 'nullable|string|max:500',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'image_boutique' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'nullable|string|max:2000',
            'whatsapp_phone' => 'nullable|string|max:20',
            'call_phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ], 
        [
            'logo.max' => 'Le logo ne doit pas dépasser 2 Mo',
            'image_boutique.max' => 'L\'image de la boutique ne doit pas dépasser 2 Mo',
            'domaine_ids.*.exists' => 'Un ou plusieurs domaines sélectionnés sont invalides',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Liste des champs modifiables (exclure les documents officiels)
            $modifiableFields = [
                'name', 'siege', 'description', 'whatsapp_phone', 
                'call_phone', 'latitude', 'longitude'
            ];

            // Mettre à jour les champs modifiables
            foreach ($modifiableFields as $field) {
                if ($request->has($field) && !is_null($request->input($field))) {
                    $entreprise->$field = $request->input($field);
                }
            }

            // Gérer la géolocalisation et l'adresse Google Maps
            if ($request->has('latitude') && $request->has('longitude')) {
                $latitude = $request->latitude;
                $longitude = $request->longitude;
                
                // Vérifier si les coordonnées ont changé
                if ($entreprise->latitude != $latitude || $entreprise->longitude != $longitude) {
                    // Mettre à jour les coordonnées
                    $entreprise->latitude = $latitude;
                    $entreprise->longitude = $longitude;
                    
                    // Convertir les coordonnées en adresse avec Google Maps
                    if (env('GOOGLE_MAPS_KEY')) {
                        try {
                            $geo = Http::timeout(10)->get("https://maps.googleapis.com/maps/api/geocode/json", [
                                'latlng' => "{$latitude},{$longitude}",
                                'key' => env('GOOGLE_MAPS_KEY'),
                                'language' => 'fr'
                            ]);

                            if ($geo->successful() && isset($geo['results'][0])) {
                                $entreprise->google_formatted_address = $geo['results'][0]['formatted_address'];
                            }
                        } catch (\Exception $e) {
                            Log::warning('Erreur lors de la géolocalisation Google Maps', ['error' => $e->getMessage()]);
                            // Continuer sans mettre à jour l'adresse
                        }
                    }
                }
            }

            // Gérer l'upload du logo
            if ($request->hasFile('logo')) {
                try {
                    $entreprise->logo = $this->uploadToCloudinary($request->file('logo'), 'logos');
                } catch (\Exception $e) {
                    throw new \Exception("Erreur lors de l'upload du logo: " . $e->getMessage());
                }
            }

            // Gérer l'upload de l'image de boutique
            if ($request->hasFile('image_boutique')) {
                try {
                    $entreprise->image_boutique = $this->uploadToCloudinary($request->file('image_boutique'), 'boutiques');
                } catch (\Exception $e) {
                    throw new \Exception("Erreur lors de l'upload de l'image boutique: " . $e->getMessage());
                }
            }

            // Sauvegarder les modifications
            $entreprise->save();

            // Mettre à jour les domaines si fournis
            if ($request->has('domaine_ids')) {
                $entreprise->domaines()->sync($request->domaine_ids);
            }

            // Recharger les relations
            $entreprise->load('domaines', 'services', 'prestataire');

            DB::commit();

            // Journaliser la modification
            Log::info('Entreprise mise à jour', [
                'entreprise_id' => $entreprise->id,
                'prestataire_id' => $user->id,
                'champs_modifies' => array_keys($request->all())
            ]);

            return response()->json([
                'message' => 'Informations de l\'entreprise mises à jour avec succès',
                'status' => 'success',
                'entreprise' => $entreprise,
                'champs_modifies' => array_intersect($modifiableFields, array_keys($request->all()))
            ], 200);

        } 
        catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('Erreur base de données lors de la mise à jour entreprise', [
                'error' => $e->getMessage(),
                'entreprise_id' => $id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Erreur de base de données',
                'status' => 'error',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la mise à jour entreprise', [
                'error' => $e->getMessage(),
                'entreprise_id' => $id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'status' => 'error',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }
}