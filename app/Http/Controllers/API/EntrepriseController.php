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
use App\Events\EntreprisePendingEvent;
use App\Models\Abonnement;

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
    Log::info('START STORE');

    $user = Auth::user();

    try {
        // -----------------------------
        // VALIDATION
        // -----------------------------
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'domaine_ids' => 'required|array|min:1',
            'domaine_ids.*' => 'integer|exists:domaines,id',

            'ifu_number' => 'required|string',
            'rccm_number' => 'required|string',
            'certificate_number' => 'required|string',

            'pdg_full_name' => 'required|string',
            'pdg_full_profession' => 'required|string',
            'role_user' => 'required|string',

            'whatsapp_phone' => 'required|string',
            'call_phone' => 'required|string',

            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',

            'ifu_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'rccm_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'certificate_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',

            'logo' => 'nullable|image|max:2048',
            'image_boutique' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // -----------------------------
        // UPLOAD FILES TO CLOUDINARY
        // -----------------------------
        $uploadedFiles = [];

        $filesToUpload = [
            'ifu_file' => ['folder' => 'documents', 'subfolder' => 'ifu'],
            'rccm_file' => ['folder' => 'documents', 'subfolder' => 'rccm'],
            'certificate_file' => ['folder' => 'documents', 'subfolder' => 'certificates'],
            'logo' => ['folder' => 'logos', 'subfolder' => null],
            'image_boutique' => ['folder' => 'boutiques', 'subfolder' => null],
        ];

        foreach ($filesToUpload as $key => $config) {
            if ($request->hasFile($key)) {
                try {
                    $uploadedFiles[$key] = $this->uploadToCloudinary(
                        $request->file($key),
                        $config['folder'],
                        $config['subfolder']
                    );
                } catch (\Throwable $e) {
                    Log::error("UPLOAD ERROR: {$key}", ['error' => $e->getMessage()]);
                    return response()->json(['error' => "Upload failed for {$key}"], 500);
                }
            }
        }

        // -----------------------------
        // PREPARE DATA FOR INSERT
        // -----------------------------
        $data = $request->except(['domaine_ids', 'logo', 'image_boutique', 'ifu_file', 'rccm_file', 'certificate_file']);
        $data['prestataire_id'] = $user->id;
        $data['status'] = 'pending';

        // Replace file fields with uploaded URLs
        foreach ($uploadedFiles as $key => $url) {
            $data[$key] = $url;
        }

        // -----------------------------
        // BEGIN TRANSACTION
        // -----------------------------
        DB::beginTransaction();

        try {
            Log::info('INSERT ENTREPRISE', $data);
            $entreprise = Entreprise::create($data);

            if (!$entreprise) {
                throw new \Exception('Failed to insert entreprise');
            }

            // Insert pivot domaines
            $pivotData = [];
            foreach ($request->domaine_ids as $domaineId) {
                $pivotData[] = [
                    'entreprise_id' => $entreprise->id,
                    'domaine_id' => $domaineId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($pivotData)) {
                DB::table('entreprise_domaine')->insert($pivotData);
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('DB TRANSACTION ERROR', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Database transaction failed'], 500);
        }

        return response()->json([
            'success' => true,
            'entreprise_id' => $entreprise->id
        ], 201);

    } catch (\Throwable $e) {
        Log::critical('FATAL ERROR STORE ENTREPRISE', [
            'error' => $e->getMessage()
        ]);

        return response()->json(['error' => 'Internal server error'], 500);
    }
}

    private function uploadToCloudinary($file, $folder, $subfolder = null) {
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