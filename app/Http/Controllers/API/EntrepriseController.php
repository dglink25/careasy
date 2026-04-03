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
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

class EntrepriseController extends Controller
{
    public function __construct()
    {
        $this->initCloudinary();
    }

    // =========================================================================
    // CLOUDINARY
    // =========================================================================

    private function initCloudinary(): void
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud.cloud_name'),
                'api_key'    => config('cloudinary.cloud.api_key'),
                'api_secret' => config('cloudinary.cloud.api_secret'),
            ],
            'url' => ['secure' => true],
        ]);

        Log::info('Cloudinary initialise avec succes', [
            'cloud_name' => config('cloudinary.cloud.cloud_name'),
        ]);
    }

    private function uploadToCloudinary($file, string $folder, ?string $subfolder = null): string
    {
        if (!$file || !$file->isValid()) {
            throw new \RuntimeException("Fichier invalide pour le champ {$folder}");
        }

        $this->initCloudinary();

        $path = $subfolder
            ? "entreprises/{$folder}/{$subfolder}"
            : "entreprises/{$folder}";

        Log::info('Uploading to Cloudinary', [
            'folder'    => $path,
            'file_name' => $file->getClientOriginalName(),
        ]);

        $result = (new UploadApi())->upload($file->getRealPath(), [
            'folder'        => $path,
            'resource_type' => 'auto',
        ]);

        if (empty($result['secure_url'])) {
            throw new \RuntimeException("Cloudinary n'a pas retourne d'URL pour {$path}");
        }

        Log::info('Upload successful', ['url' => $result['secure_url'], 'folder' => $path]);

        return $result['secure_url'];
    }

    // =========================================================================
    // PUBLIC ROUTES
    // =========================================================================

    public function getFormData()
    {
        return response()->json([
            'domaines' => Domaine::orderBy('name')->get(),
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

        $entreprises->each(fn($e) => $e->append([
            'is_in_trial_period',
            'trial_days_remaining',
            'trial_status',
        ]));

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
        return response()->json(
            Entreprise::where('status', 'validated')
                ->whereHas('domaines', fn($q) => $q->where('domaines.id', $domaineId))
                ->with('domaines', 'services')
                ->get()
        );
    }

    public function search(Request $request)
    {
        $s = $request->query('q', '');

        return response()->json(
            Entreprise::where('status', 'validated')
                ->where('name', 'LIKE', "%{$s}%")
                ->with('domaines', 'services')
                ->get()
        );
    }

    // =========================================================================
    // STORE
    // Pas de DB::beginTransaction() — incompatible avec Neon pgBouncer
    // en mode "transaction pooling".
    // =========================================================================

    public function store(Request $request)
    {
        Log::info('START STORE');

        $user = Auth::user();

        // ── 1. VALIDATION ──────────────────────────────────────────────────
        $validator = Validator::make($request->all(), [
            'name'                => 'required|string|max:255',
            'domaine_ids'         => 'required|array|min:1',
            'domaine_ids.*'       => 'integer',
            'ifu_number'          => 'required|string',
            'rccm_number'         => 'required|string',
            'certificate_number'  => 'required|string',
            'pdg_full_name'       => 'required|string',
            'pdg_full_profession' => 'required|string',
            'role_user'           => 'required|string',
            'whatsapp_phone'      => 'required|string',
            'call_phone'          => 'required|string',
            'latitude'            => 'required|numeric|between:-90,90',
            'longitude'           => 'required|numeric|between:-180,180',
            'ifu_file'            => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'rccm_file'           => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'certificate_file'    => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'logo'                => 'nullable|image|max:2048',
            'image_boutique'      => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // ── 2. VÉRIFIER LES DOMAINES (requete simple hors transaction) ─────
        $requestedIds = array_map('intval', $request->domaine_ids);

        $foundIds = DB::table('domaines')
            ->whereIn('id', $requestedIds)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        $invalidIds = array_diff($requestedIds, $foundIds);
        if (!empty($invalidIds)) {
            return response()->json([
                'error' => 'Domaines introuvables : ' . implode(', ', $invalidIds),
            ], 422);
        }

        // ── 3. UPLOADS CLOUDINARY (avant toute ecriture DB) ───────────────
        $uploads = [];
        $filemap = [
            'ifu_file'         => ['documents', 'ifu'],
            'rccm_file'        => ['documents', 'rccm'],
            'certificate_file' => ['documents', 'certificates'],
            'logo'             => ['logos',     null],
            'image_boutique'   => ['boutiques', null],
        ];

        foreach ($filemap as $field => [$folder, $sub]) {
            if ($request->hasFile($field)) {
                try {
                    $uploads[$field] = $this->uploadToCloudinary($request->file($field), $folder, $sub);
                } catch (\Throwable $e) {
                    Log::error("Upload failed [{$field}]", ['error' => $e->getMessage()]);
                    return response()->json([
                        'error' => "Echec upload {$field}: " . $e->getMessage(),
                    ], 500);
                }
            }
        }

        // ── 4. INSÉRER L'ENTREPRISE ────────────────────────────────────────
        $now = now();

        $insertData = [
            'name'                     => $request->name,
            'ifu_number'               => $request->ifu_number,
            'rccm_number'              => $request->rccm_number,
            'certificate_number'       => $request->certificate_number,
            'pdg_full_name'            => $request->pdg_full_name,
            'pdg_full_profession'      => $request->pdg_full_profession,
            'role_user'                => $request->role_user,
            'whatsapp_phone'           => $request->whatsapp_phone,
            'call_phone'               => $request->call_phone,
            'latitude'                 => $request->latitude,
            'longitude'                => $request->longitude,
            'siege'                    => $request->siege,
            'google_formatted_address' => $request->google_formatted_address,
            'prestataire_id'           => $user->id,
            'status'                   => 'pending',
            'ifu_file'                 => $uploads['ifu_file']         ?? null,
            'rccm_file'                => $uploads['rccm_file']        ?? null,
            'certificate_file'         => $uploads['certificate_file'] ?? null,
            'logo'                     => $uploads['logo']             ?? null,
            'image_boutique'           => $uploads['image_boutique']   ?? null,
            'created_at'               => $now,
            'updated_at'               => $now,
        ];

        Log::info('INSERT ENTREPRISE', $insertData);

        try {
            $entrepriseId = DB::table('entreprises')->insertGetId($insertData);
        } catch (\Throwable $e) {
            Log::error('INSERT ENTREPRISE FAILED', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => "Impossible de creer l'entreprise : " . $e->getMessage(),
            ], 500);
        }

        Log::info('Entreprise created', ['id' => $entrepriseId]);

        // ── 5. INSÉRER LES PIVOTS DOMAINES ────────────────────────────────
        // La table entreprise_domaine n'a PAS de colonnes created_at/updated_at
        // => on insere uniquement entreprise_id et domaine_id

        foreach ($foundIds as $domaineId) {
            try {
                DB::table('entreprise_domaine')->insert([
                    'entreprise_id' => $entrepriseId,
                    'domaine_id'    => $domaineId,
                ]);
            } catch (\Throwable $e) {
                Log::warning("Pivot domaine {$domaineId} echoue", [
                    'error'         => $e->getMessage(),
                    'entreprise_id' => $entrepriseId,
                ]);
            }
        }

        Log::info('Store completed', ['entreprise_id' => $entrepriseId]);

        return response()->json([
            'success'       => true,
            'entreprise_id' => $entrepriseId,
        ], 201);
    }

    // =========================================================================
    // COMPLETE PROFILE
    // =========================================================================

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
            return response()->json(['message' => 'Action non autorisee : entreprise non validee'], 403);
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
                'message'    => 'Profil entreprise mis a jour',
                'entreprise' => $entreprise,
            ]);
        } catch (\Throwable $e) {
            Log::error('CompleteProfile error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors de la mise a jour',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifie', 'status' => 'error'], 401);
        }

        $entreprise = Entreprise::where('id', $id)
            ->where('prestataire_id', $user->id)
            ->first();

        if (!$entreprise) {
            return response()->json([
                'message' => 'Entreprise non trouvee ou permissions insuffisantes',
                'status'  => 'error',
            ], 404);
        }

        if ($entreprise->status !== 'validated') {
            return response()->json([
                'message'        => 'Seules les entreprises validees peuvent etre modifiees',
                'status'         => 'error',
                'current_status' => $entreprise->status,
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'           => 'nullable|string|max:255',
            'domaine_ids'    => 'nullable|array',
            'domaine_ids.*'  => 'integer',
            'siege'          => 'nullable|string|max:500',
            'logo'           => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'image_boutique' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description'    => 'nullable|string|max:2000',
            'whatsapp_phone' => 'nullable|string|max:20',
            'call_phone'     => 'nullable|string|max:20',
            'latitude'       => 'nullable|numeric|between:-90,90',
            'longitude'      => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'status'  => 'error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Verifier les domaines avant toute modification
        $newDomaineIds = null;
        if ($request->has('domaine_ids') && is_array($request->domaine_ids)) {
            $raw = array_map('intval', $request->domaine_ids);

            if (!empty($raw)) {
                $found      = DB::table('domaines')->whereIn('id', $raw)->pluck('id')->map(fn($i) => (int)$i)->toArray();
                $invalidIds = array_diff($raw, $found);

                if (!empty($invalidIds)) {
                    return response()->json([
                        'message' => 'Domaines invalides : ' . implode(', ', $invalidIds),
                        'status'  => 'error',
                    ], 422);
                }
                $newDomaineIds = $found;
            } else {
                $newDomaineIds = [];
            }
        }

        try {
            $modifiable = ['name', 'siege', 'description', 'whatsapp_phone', 'call_phone', 'latitude', 'longitude'];
            foreach ($modifiable as $field) {
                if ($request->filled($field)) {
                    $entreprise->$field = $request->input($field);
                }
            }

            if ($request->filled('latitude') && $request->filled('longitude')) {
                $lat = $request->latitude;
                $lng = $request->longitude;

                if ($entreprise->latitude != $lat || $entreprise->longitude != $lng) {
                    $entreprise->latitude  = $lat;
                    $entreprise->longitude = $lng;

                    if (env('GOOGLE_MAPS_KEY')) {
                        try {
                            $geo = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                                'latlng' => "{$lat},{$lng}",
                                'key'    => env('GOOGLE_MAPS_KEY'),
                                'language' => 'fr',
                            ]);
                            if ($geo->successful() && isset($geo['results'][0])) {
                                $entreprise->google_formatted_address = $geo['results'][0]['formatted_address'];
                            }
                        } catch (\Throwable $e) {
                            Log::warning('Geocoding error', ['error' => $e->getMessage()]);
                        }
                    }
                }
            }

            if ($request->hasFile('logo')) {
                $entreprise->logo = $this->uploadToCloudinary($request->file('logo'), 'logos');
            }

            if ($request->hasFile('image_boutique')) {
                $entreprise->image_boutique = $this->uploadToCloudinary($request->file('image_boutique'), 'boutiques');
            }

            $entreprise->save();

            // Mise a jour des domaines sans created_at/updated_at
            if ($newDomaineIds !== null) {
                DB::table('entreprise_domaine')
                    ->where('entreprise_id', $entreprise->id)
                    ->delete();

                foreach ($newDomaineIds as $domaineId) {
                    try {
                        DB::table('entreprise_domaine')->insert([
                            'entreprise_id' => $entreprise->id,
                            'domaine_id'    => $domaineId,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning("Pivot update domaine {$domaineId} echoue", ['error' => $e->getMessage()]);
                    }
                }
            }

            $entreprise->load('domaines', 'services', 'prestataire');

            return response()->json([
                'message'    => 'Informations mises a jour avec succes',
                'status'     => 'success',
                'entreprise' => $entreprise,
            ]);

        } catch (\Throwable $e) {
            Log::error('Error updating entreprise', [
                'error'         => $e->getMessage(),
                'entreprise_id' => $id,
                'user_id'       => $user->id,
            ]);

            return response()->json([
                'message' => 'Erreur lors de la mise a jour',
                'status'  => 'error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}