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

class EntrepriseController extends Controller{
    public function __construct()  {
        $this->initializeCloudinary();
    }
    
    private function initializeCloudinary()
    {
        try {
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => config('cloudinary.cloud.cloud_name'),
                    'api_key'    => config('cloudinary.cloud.api_key'),
                    'api_secret' => config('cloudinary.cloud.api_secret'),
                ],
                'url' => [
                    'secure' => config('cloudinary.url.secure', true)
                ]
            ]);
            
            Log::info('Cloudinary initialise avec succes', [
                'cloud_name' => config('cloudinary.cloud.cloud_name')
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur initialisation Cloudinary', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    public function getFormData()  {
        return response()->json([
            'domaines' => Domaine::orderBy('name')->get()
        ]);
    }

    public function index()
    {
        return Entreprise::with('domaines', 'service')
            ->where('status', 'validated')
            ->get();
    }

    public function mine()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Non authentifie'], 401);
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
   
    public function show($id)
    {
        $entreprise = Entreprise::with('domaines', 'services', 'prestataire')->find($id);

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non trouvee'], 404);
        }

        return response()->json($entreprise);
    }

    public function indexByDomaine($domaineId)
    {
        $entreprises = Entreprise::where('status', 'validated')
            ->whereHas('domaines', function ($q) use ($domaineId) {
                $q->where('domaines.id', $domaineId);
            })
            ->with('domaines', 'services')
            ->get();

        return response()->json($entreprises);
    }

    public function search(Request $request)
    {
        $s = $request->query('q');

        $entreprises = Entreprise::where('status', 'validated')
            ->where('name', 'LIKE', "%$s%")
            ->with('domaines', 'services')
            ->get();

        return response()->json($entreprises);
    }

    public function store(Request $request)
    {
        Log::info('Donnees recues:', $request->all());

        $user = Auth::user();

        $existantes = Entreprise::where('prestataire_id', $user->id)->get();

        foreach ($existantes as $e) {
            if ($e->status === 'pending') {
                return response()->json([
                    'message'         => 'Une demande est deja en cours de traitement. Veuillez patienter.',
                    'status'          => 'pending',
                    'entreprise_name' => $e->name,
                ], 409);
            }

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
                            ? "Votre entreprise \"{$e->name}\" est en periode d'essai ({$joursRestants} jours restants). Souscrivez un abonnement payant pour creer une nouvelle entreprise."
                            : "Votre periode d'essai pour \"{$e->name}\" est terminee. Souscrivez un abonnement payant pour continuer.",
                        'status'          => 'validated',
                        'trial_status'    => $isInTrial ? 'in_trial' : 'expired',
                        'days_remaining'  => $joursRestants,
                        'entreprise_name' => $e->name,
                    ], 403);
                }
            }
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
            'whatsapp_phone'     => 'required|string',
            'call_phone'         => 'required|string',
            'certificate_file'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'siege'              => 'nullable|string',
            'logo'               => 'nullable|image|max:2048',
            'image_boutique'     => 'nullable|image|max:2048',
            'latitude'           => 'required|numeric|between:-90,90',
            'longitude'          => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation echouee: ' . $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $uploadedFiles = [];
        $uploadErrors = [];
        
        try {
            if ($request->hasFile('logo')) {
                try {
                    $uploadedFiles['logo'] = $this->uploadToCloudinary($request->file('logo'), 'logos');
                } catch (\Exception $e) {
                    $uploadErrors['logo'] = $e->getMessage();
                    Log::error('Upload logo failed', ['error' => $e->getMessage()]);
                }
            }

            if ($request->hasFile('image_boutique')) {
                try {
                    $uploadedFiles['image_boutique'] = $this->uploadToCloudinary($request->file('image_boutique'), 'boutiques');
                } catch (\Exception $e) {
                    $uploadErrors['image_boutique'] = $e->getMessage();
                    Log::error('Upload image boutique failed', ['error' => $e->getMessage()]);
                }
            }

            if ($request->hasFile('ifu_file')) {
                try {
                    $uploadedFiles['ifu_file'] = $this->uploadToCloudinary($request->file('ifu_file'), 'documents', 'ifu');
                } catch (\Exception $e) {
                    $uploadErrors['ifu_file'] = $e->getMessage();
                    Log::error('Upload IFU failed', ['error' => $e->getMessage()]);
                }
            }

            if ($request->hasFile('rccm_file')) {
                try {
                    $uploadedFiles['rccm_file'] = $this->uploadToCloudinary($request->file('rccm_file'), 'documents', 'rccm');
                } catch (\Exception $e) {
                    $uploadErrors['rccm_file'] = $e->getMessage();
                    Log::error('Upload RCCM failed', ['error' => $e->getMessage()]);
                }
            }

            if ($request->hasFile('certificate_file')) {
                try {
                    $uploadedFiles['certificate_file'] = $this->uploadToCloudinary($request->file('certificate_file'), 'documents', 'certificates');
                } catch (\Exception $e) {
                    $uploadErrors['certificate_file'] = $e->getMessage();
                    Log::error('Upload certificate failed', ['error' => $e->getMessage()]);
                }
            }

            $requiredFiles = ['ifu_file', 'rccm_file', 'certificate_file'];
            foreach ($requiredFiles as $requiredFile) {
                if ($request->hasFile($requiredFile) && !isset($uploadedFiles[$requiredFile])) {
                    throw new \Exception("Upload failed for required file: {$requiredFile}");
                }
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Upload des fichiers obligatoires echoue',
                'error' => $e->getMessage(),
                'details' => $uploadErrors
            ], 500);
        }

        DB::beginTransaction();
        
        try {
            $data = $request->except(['domaine_ids']);
            $data['prestataire_id'] = Auth::id();
            $data['status'] = 'pending';
            $data['latitude'] = $request->latitude;
            $data['longitude'] = $request->longitude;
            
            foreach ($uploadedFiles as $key => $url) {
                $data[$key] = $url;
            }

            try {
                $geo = Http::timeout(10)->get("https://maps.googleapis.com/maps/api/geocode/json", [
                    'latlng' => "{$request->latitude},{$request->longitude}",
                    'key' => env('GOOGLE_MAPS_KEY')
                ]);

                if ($geo->successful() && isset($geo['results'][0])) {
                    $data['google_formatted_address'] = $geo['results'][0]['formatted_address'];
                }
            } catch (\Exception $e) {
                Log::warning('Geocoding failed', ['error' => $e->getMessage()]);
            }

            $entreprise = Entreprise::create($data);
            
            if (!empty($request->domaine_ids)) {
                $pivotData = [];
                foreach ($request->domaine_ids as $domaineId) {
                    $pivotData[] = [
                        'entreprise_id' => $entreprise->id,
                        'domaine_id' => $domaineId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
                DB::table('entreprise_domaine')->insert($pivotData);
            }

            DB::commit();

            try {
                $admins = User::where('role', 'admin')->get();
                
                foreach ($admins as $admin) {
                    try {
                        $admin->notify(new NewEntrepriseCreatedNotification($entreprise, $request->user()));
                        event(new \App\Events\EntreprisePendingEvent($entreprise, $admin->id));
                    } catch (\Exception $e) {
                        Log::error('Admin notification failed', [
                            'admin_id' => $admin->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                Log::info('Notifications sent to admins', [
                    'entreprise_id' => $entreprise->id,
                    'admins_notified' => $admins->count()
                ]);

            } catch (\Exception $e) {
                Log::error('Error sending notifications', [
                    'error' => $e->getMessage(),
                    'entreprise_id' => $entreprise->id
                ]);
            }
           
            $entreprise->load('domaines', 'prestataire');

            $responseMessage = 'Entreprise creee et envoyee en validation';
            if (!empty($uploadErrors)) {
                $responseMessage .= ' (Attention: certains fichiers optionnels nont pas pu etre uploades)';
            }

            return response()->json([
                'message' => $responseMessage,
                'entreprise' => $entreprise,
                'upload_warnings' => !empty($uploadErrors) ? $uploadErrors : null
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Database error creating entreprise', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Erreur lors de la creation de lentreprise',
                'error' => $e->getMessage(),
                'details' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    private function uploadToCloudinary($file, $folder, $subfolder = null)
    {
        if (!$file || !$file->isValid()) {
            throw new \Exception('Fichier invalide');
        }

        $this->initializeCloudinary();

        $folderPath = $subfolder
            ? "entreprises/{$folder}/{$subfolder}"
            : "entreprises/{$folder}";

        try {
            Log::info('Uploading to Cloudinary', [
                'folder' => $folderPath,
                'file_name' => $file->getClientOriginalName()
            ]);

            $result = (new UploadApi())->upload(
                $file->getRealPath(),
                [
                    'folder' => $folderPath,
                    'resource_type' => 'auto',
                ]
            );

            if (!isset($result['secure_url'])) {
                throw new \Exception('Cloudinary did not return secure URL');
            }

            Log::info('Upload successful', [
                'url' => $result['secure_url'],
                'folder' => $folderPath
            ]);

            return $result['secure_url'];

        } catch (\Cloudinary\Api\Exception\ApiError $e) {
            Log::error('Cloudinary API error', [
                'error' => $e->getMessage(),
                'folder' => $folderPath
            ]);
            throw new \Exception('Cloudinary error: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Upload error', [
                'error' => $e->getMessage(),
                'folder' => $folderPath
            ]);
            throw new \Exception('Upload failed: ' . $e->getMessage());
        }
    }

    public function completeProfile(Request $request, $id)
    {
        $user = Auth::user();

        $entreprise = Entreprise::where('id', $id)
            ->where('prestataire_id', $user->id)
            ->first();

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise introuvable ou non autorisee'], 404);
        }

        if ($entreprise->status !== 'validated') {
            return response()->json(['message' => 'Action non autorisee: entreprise non validee'], 403);
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

            $entreprise->fill($request->only(['siege', 'whatsapp_phone', 'call_phone', 'status_online']));
            $entreprise->save();

            $entreprise->load('domaines', 'services');

            return response()->json([
                'message' => 'Profil entreprise mis a jour',
                'entreprise' => $entreprise
            ]);
        } catch (\Exception $e) {
            Log::error('CompleteProfile error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors de la mise a jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Non authentifie',
                'status' => 'error'
            ], 401);
        }

        $entreprise = Entreprise::where('id', $id)
            ->where('prestataire_id', $user->id)
            ->first();

        if (!$entreprise) {
            return response()->json([
                'message' => 'Entreprise non trouvee ou vous navez pas les permissions necessaires',
                'status' => 'error'
            ], 404);
        }

        if ($entreprise->status !== 'validated') {
            return response()->json([
                'message' => 'Seules les entreprises validees peuvent etre modifiees',
                'status' => 'error',
                'current_status' => $entreprise->status
            ], 403);
        }

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
        ], [
            'logo.max' => 'Le logo ne doit pas depasser 2 Mo',
            'image_boutique.max' => 'Limage de la boutique ne doit pas depasser 2 Mo',
            'domaine_ids.*.exists' => 'Un ou plusieurs domaines selectionnes sont invalides',
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
            $modifiableFields = [
                'name', 'siege', 'description', 'whatsapp_phone', 
                'call_phone', 'latitude', 'longitude'
            ];

            foreach ($modifiableFields as $field) {
                if ($request->has($field) && !is_null($request->input($field))) {
                    $entreprise->$field = $request->input($field);
                }
            }

            if ($request->has('latitude') && $request->has('longitude')) {
                $latitude = $request->latitude;
                $longitude = $request->longitude;
                
                if ($entreprise->latitude != $latitude || $entreprise->longitude != $longitude) {
                    $entreprise->latitude = $latitude;
                    $entreprise->longitude = $longitude;
                    
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
                            Log::warning('Geocoding error', ['error' => $e->getMessage()]);
                        }
                    }
                }
            }

            if ($request->hasFile('logo')) {
                try {
                    $entreprise->logo = $this->uploadToCloudinary($request->file('logo'), 'logos');
                } catch (\Exception $e) {
                    throw new \Exception("Logo upload failed: " . $e->getMessage());
                }
            }

            if ($request->hasFile('image_boutique')) {
                try {
                    $entreprise->image_boutique = $this->uploadToCloudinary($request->file('image_boutique'), 'boutiques');
                } catch (\Exception $e) {
                    throw new \Exception("Boutique image upload failed: " . $e->getMessage());
                }
            }

            $entreprise->save();

            if ($request->has('domaine_ids')) {
                DB::table('entreprise_domaine')->where('entreprise_id', $entreprise->id)->delete();
                
                $pivotData = [];
                foreach ($request->domaine_ids as $domaineId) {
                    $pivotData[] = [
                        'entreprise_id' => $entreprise->id,
                        'domaine_id' => $domaineId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
                DB::table('entreprise_domaine')->insert($pivotData);
            }

            $entreprise->load('domaines', 'services', 'prestataire');

            DB::commit();

            Log::info('Entreprise updated', [
                'entreprise_id' => $entreprise->id,
                'prestataire_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Informations de lentreprise mises a jour avec succes',
                'status' => 'success',
                'entreprise' => $entreprise
            ], 200);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('Database error updating entreprise', [
                'error' => $e->getMessage(),
                'entreprise_id' => $id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Erreur de base de donnees',
                'status' => 'error',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating entreprise', [
                'error' => $e->getMessage(),
                'entreprise_id' => $id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Erreur lors de la mise a jour',
                'status' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}