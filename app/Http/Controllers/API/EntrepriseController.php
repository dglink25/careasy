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
    // Valeurs par défaut de Cloudinary (en cas d'échec de lecture des variables d'environnement)
    private $cloudinaryConfig = [
        'cloud_name' => 'dsumeoiga',
        'api_key' => '571431578845174',
        'api_secret' => 'JUkkERciRqqYAset1e3XBuCuzuE',
        'url' => 'cloudinary://571431578845174:JUkkERciRqqYAset1e3XBuCuzuE@dsumeoiga'
    ];
    
    public function __construct() {
        // Vérifier et initialiser Cloudinary au démarrage
        $this->initializeCloudinary();
    }
    
    /**
     * Initialise Cloudinary avec les variables d'environnement ou valeurs par défaut
     */
    private function initializeCloudinary() {
        try {
            // Essayer de récupérer depuis .env
            $cloudName = env('CLOUDINARY_CLOUD_NAME');
            $apiKey = env('CLOUDINARY_API_KEY');
            $apiSecret = env('CLOUDINARY_API_SECRET');
            $cloudinaryUrl = env('CLOUDINARY_URL');
            
            // Si les variables sont null, utiliser les valeurs par défaut
            if (is_null($cloudName) || is_null($apiKey) || is_null($apiSecret)) {
                Log::warning('Variables Cloudinary non trouvées dans .env, utilisation des valeurs par défaut', [
                    'cloud_name_from_env' => $cloudName,
                    'api_key_from_env' => $apiKey,
                    'using_defaults' => true
                ]);
                
                $cloudName = $this->cloudinaryConfig['cloud_name'];
                $apiKey = $this->cloudinaryConfig['api_key'];
                $apiSecret = $this->cloudinaryConfig['api_secret'];
                $cloudinaryUrl = $this->cloudinaryConfig['url'];
            }
            
            // Configuration de Cloudinary
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ]
            ]);
            
            Log::info('Cloudinary configuré avec succès', [
                'cloud_name' => $cloudName,
                'using_defaults' => is_null(env('CLOUDINARY_CLOUD_NAME'))
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'initialisation de Cloudinary', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Impossible de configurer Cloudinary: ' . $e->getMessage());
        }
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

 public function store(Request $request)
{
    Log::info('Données reçues:', $request->all());

    $user = Auth::user();

    // ───────────────── VALIDATION ─────────────────
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
        'whatsapp_phone'     => 'required|string',
        'call_phone'         => 'required|string',
        'certificate_file'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        'latitude'           => 'required|numeric|between:-90,90',
        'longitude'          => 'required|numeric|between:-180,180',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors()
        ], 422);
    }

    // ───────────────── UPLOAD (hors transaction) ─────────────────
    $uploadedFiles = [];

    try {
        foreach ([
            'ifu_file' => ['documents', 'ifu'],
            'rccm_file' => ['documents', 'rccm'],
            'certificate_file' => ['documents', 'certificates'],
        ] as $field => $path) {

            if ($request->hasFile($field)) {
                $uploadedFiles[$field] = $this->uploadToCloudinary(
                    $request->file($field),
                    $path[0],
                    $path[1]
                );
            } else {
                throw new \Exception("Fichier obligatoire manquant: {$field}");
            }
        }

    } catch (\Throwable $e) {
        Log::error('Erreur upload fichiers', ['error' => $e->getMessage()]);
        return response()->json([
            'message' => 'Erreur upload fichiers',
            'error' => $e->getMessage()
        ], 500);
    }

    // ───────────────── PRÉPARATION DATA ─────────────────
    $data = $request->except(['domaine_ids']);
    $data['prestataire_id'] = $user->id;
    $data['status'] = 'pending';

    foreach ($uploadedFiles as $key => $url) {
        $data[$key] = $url;
    }

    // Google Maps (hors transaction)
    try {
        $geo = Http::timeout(5)->get("https://maps.googleapis.com/maps/api/geocode/json", [
            'latlng' => "{$request->latitude},{$request->longitude}",
            'key' => env('GOOGLE_MAPS_KEY')
        ]);

        if ($geo->successful() && isset($geo['results'][0])) {
            $data['google_formatted_address'] = $geo['results'][0]['formatted_address'];
        }

    } catch (\Throwable $e) {
        Log::warning('Erreur Google Maps', ['error' => $e->getMessage()]);
    }

    // ───────────────── TRANSACTION COURTE ─────────────────
    try {

        DB::beginTransaction();

        // 1. Création entreprise
       try {
            Log::info('DATA AVANT INSERT', $data);

            $entreprise = Entreprise::create($data);

            Log::info('ENTREPRISE CRÉÉE', ['id' => $entreprise->id]);

        } 
        catch (\Throwable $e) {
            Log::error('ERREUR INSERT ENTREPRISE', [
                'message' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }

        // 2. Sync domaines (SAFE)
        try {
            $ids = array_filter($request->domaine_ids);

            if (!empty($ids)) {
                $entreprise->domaines()->sync($ids);
            }

        } catch (\Throwable $e) {
            Log::error('Erreur sync domaines', [
                'ids' => $request->domaine_ids,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        DB::commit();

    } catch (\Throwable $e) {
        DB::rollBack();

        Log::error('Transaction échouée', [
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'message' => 'Erreur base de données',
            'error' => $e->getMessage()
        ], 500);
    }

    // ───────────────── POST-TRAITEMENT ─────────────────
    try {
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            $admin->notify(new NewEntrepriseCreatedNotification($entreprise, $user));
        }

    } catch (\Throwable $e) {
        Log::warning('Erreur notifications', ['error' => $e->getMessage()]);
    }

    return response()->json([
        'message' => 'Entreprise créée avec succès',
        'entreprise' => $entreprise->load('domaines')
    ], 201);
}

  

    private function uploadToCloudinary($file, $folder, $subfolder = null) {
        // Validation du fichier
        if (!$file || !$file->isValid()) {
            throw new \Exception('Fichier invalide pour upload');
        }

        $folderPath = $subfolder
            ? "{$folder}/{$subfolder}"
            : $folder;

        try {
            // Récupérer la configuration Cloudinary
            $cloudName = env('CLOUDINARY_CLOUD_NAME');
            $apiKey = env('CLOUDINARY_API_KEY');
            $apiSecret = env('CLOUDINARY_API_SECRET');
            
            // Si variables null, utiliser valeurs par défaut
            if (is_null($cloudName) || is_null($apiKey) || is_null($apiSecret)) {
                Log::warning('Utilisation des valeurs par défaut pour Cloudinary dans upload');
                $cloudName = $this->cloudinaryConfig['cloud_name'];
                $apiKey = $this->cloudinaryConfig['api_key'];
                $apiSecret = $this->cloudinaryConfig['api_secret'];
            }
            
            // Reconfigurer Cloudinary pour être sûr
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ]
            ]);
            
            // Vérifier que la configuration est valide
            if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
                throw new \Exception('Configuration Cloudinary incomplète');
            }
            
            Log::info('Tentative d\'upload vers Cloudinary', [
                'folder' => $folderPath,
                'file_name' => $file->getClientOriginalName(),
                'cloud_name' => $cloudName
            ]);
            
            $result = (new UploadApi())->upload(
                $file->getRealPath(),
                [
                    'folder' => $folderPath,
                    'resource_type' => 'auto',
                ]
            );

            if (!isset($result['secure_url'])) {
                throw new \Exception('Cloudinary n\'a pas retourné d\'URL sécurisée');
            }

            Log::info('Upload Cloudinary réussi', [
                'url' => $result['secure_url'],
                'folder' => $folderPath
            ]);

            return $result['secure_url'];
        } 
        catch (\Cloudinary\Api\Exception\ApiError $e) {
            Log::error('Erreur API Cloudinary:', [
                'error' => $e->getMessage(),
                'folder' => $folderPath,
                'code' => $e->getCode()
            ]);
            throw new \Exception('Erreur Cloudinary: ' . $e->getMessage());
        }
        catch (\Exception $e) {
            Log::error('Erreur upload Cloudinary:', [
                'error' => $e->getMessage(),
                'folder' => $folderPath,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Impossible d\'uploader le fichier: ' . $e->getMessage());
        }
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
            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
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
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'status' => 'error',
                'error' => $e->getMessage(),
                'details' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
}