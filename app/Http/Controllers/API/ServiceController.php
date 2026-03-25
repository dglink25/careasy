<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use App\Models\Domaine;

class ServiceController extends Controller{
    public function __construct(){
        Configuration::instance([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    public function mine() {
        $user = Auth::user();

        $services = Service::with(['entreprise', 'domaine'])
            ->where('prestataire_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($service) {
                return $this->formatServiceResponse($service);
            });

        return response()->json($services);
    }

    public function index(){
        $services = Service::with(['entreprise', 'domaine'])
            ->where('is_visibility', true) 
            ->whereHas('entreprise', fn($q) => 
                $q->where('status', 'validated')
            )
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($service) => $this->formatServiceResponse($service));

        return response()->json($services);
    }

    public function domaines() {
        return Domaine::get();
    }

    private function formatServiceResponse($service) {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'price' => $service->price,
            'price_promo' => $service->price_promo,
            'is_price_on_request' => $service->is_price_on_request,
            'has_promo' => $service->has_promo,
            'is_promo_active' => $service->isPromoActive(),
            'discount_percentage' => $service->discount_percentage,
            'descriptions' => $service->descriptions,
            'medias' => $service->medias,
            'is_always_open' => $service->is_always_open,
            'start_time' => $service->start_time,
            'end_time' => $service->end_time,
            'schedule' => $service->schedule,
            'is_visibility' => $service->is_visibility ?? true, // ⭐ AJOUTÉ
            'entreprise' => [
                'id' => $service->entreprise->id,
                'name' => $service->entreprise->name,
                'logo' => $service->entreprise->logo,
                'call_phone' => $service->entreprise->call_phone,
                'whatsapp_phone' => $service->entreprise->whatsapp_phone,
                'email' => $service->entreprise->email,
                'address' => $service->entreprise->address,
                'status' => $service->entreprise->status,
            ],
            'domaine' => $service->domaine,
        ];
    }

    public function search(Request $request) {
        $query = $request->get('q');
        $type = $request->get('type', 'all');
        
        $results = [];

        // Recherche de services
        if ($type === 'all' || $type === 'service') {
            $services = Service::with(['entreprise', 'domaine'])
                ->where(function($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('descriptions', 'LIKE', "%{$query}%");
                })
                ->where('is_visibility', true) 
                ->whereHas('entreprise', function($q) {
                    $q->where('status', 'validated');
                })
                ->limit(10)
                ->get()
                ->map(function ($service) {
                    $data = $this->formatServiceResponse($service);
                    $data['type'] = 'service';
                    return $data;
                });
            
            $results = array_merge($results, $services->toArray());
        }

        // Recherche d'entreprises
        if ($type === 'all' || $type === 'entreprise') {
            $entreprises = Entreprise::with('domaines')
                ->where(function($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('description', 'LIKE', "%{$query}%");
                })
                ->where('status', 'validated')
                ->limit(10)
                ->get()
                ->map(function ($entreprise) {
                    return [
                        'id' => $entreprise->id,
                        'name' => $entreprise->name,
                        'logo' => $entreprise->logo,
                        'description' => $entreprise->description,
                        'type' => 'entreprise',
                        'status' => $entreprise->status,
                        'email' => $entreprise->email,
                        'call_phone' => $entreprise->call_phone,
                        'whatsapp_phone' => $entreprise->whatsapp_phone,
                        'address' => $entreprise->address,
                    ];
                });
            
            $results = array_merge($results, $entreprises->toArray());
        }

        // Mélanger les résultats pour avoir un mix
        shuffle($results);
        
        return response()->json($results);
    }

    public function store(Request $request){
        Log::info('Création service - Données reçues:', $request->all());

        $user = Auth::user();

        $entreprise = Entreprise::find($request->entreprise_id);
    
        if ($entreprise && $entreprise->isInTrialPeriod()) {
            if (!$entreprise->canAddService()) {
                return response()->json([
                    'message' => 'Vous avez atteint la limite de services autorisés pendant la période d\'essai',
                    'max_services' => $entreprise->max_services_allowed,
                    'current_services' => $entreprise->services()->count()
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'entreprise_id' => 'required|exists:entreprises,id',
            'domaine_id'    => 'required|exists:domaines,id',
            'name'          => 'required|string|max:255',
            'price'         => 'nullable|numeric',
            'price_promo'   => 'nullable|numeric|lt:price',
            'is_price_on_request' => 'boolean',
            'has_promo'     => 'boolean',
            'promo_start_date' => 'nullable|date',
            'promo_end_date'   => 'nullable|date|after:promo_start_date',
            'descriptions'  => 'nullable|string',
            'is_always_open' => 'nullable|boolean',
            'schedule'      => 'nullable|array',
            'schedule.*.is_open' => 'boolean',
            'is_always_open'  => true,
            'schedule.*.start' => 'required_if:schedule.*.is_open,true|nullable|date_format:H:i',
            'schedule.*.end'   => 'required_if:schedule.*.is_open,true|nullable|date_format:H:i|after:schedule.*.start',
            'medias.*'      => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $entreprise = Entreprise::where('id', $request->entreprise_id)
            ->where('prestataire_id', $user->id)
            ->first();

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise non autorisée'], 403);
        }

        if ($entreprise->status !== 'validated') {
            return response()->json(['message' => 'Entreprise non validée'], 403);
        }

        if (!$entreprise->domaines->pluck('id')->contains($request->domaine_id)) {
            return response()->json(['message' => 'Domaine non autorisé pour cette entreprise'], 403);
        }

        try {
            $medias = [];
            if ($request->hasFile('medias')) {
                foreach ($request->file('medias') as $file) {
                    $medias[] = $this->uploadToCloudinary($file, 'services', $user->id);
                }
            }

            // Préparer les données
            $data = [
                'entreprise_id' => $entreprise->id,
                'prestataire_id' => $user->id,
                'domaine_id' => $request->domaine_id,
                'name' => $request->name,
                'price' => $request->price,
                'price_promo' => $request->price_promo,
                'is_price_on_request' => $request->boolean('is_price_on_request', false),
                'has_promo' => $request->boolean('has_promo', false),
                'promo_start_date' => $request->promo_start_date,
                'promo_end_date' => $request->promo_end_date,
                'descriptions' => $request->descriptions ?? '',
                'medias' => $medias,
                'is_always_open' => $request->boolean('is_always_open', false),
            ];

            // Validation des règles de prix
            if ($data['is_price_on_request']) {
                $data['price'] = null;
                $data['price_promo'] = null;
                $data['has_promo'] = false;
            }

            if ($data['has_promo'] && !$data['price_promo']) {
                return response()->json([
                    'errors' => ['price_promo' => ['Le prix promotionnel est requis quand la promotion est activée']]
                ], 422);
            }

            if ($data['has_promo'] && $data['price_promo'] && $data['price'] && $data['price_promo'] >= $data['price']) {
                return response()->json([
                    'errors' => ['price_promo' => ['Le prix promotionnel doit être inférieur au prix normal']]
                ], 422);
            }

            // Gestion des horaires
            if ($request->boolean('is_always_open')) {
                $data['is_open_24h'] = true;
                $data['schedule'] = null;
                $data['start_time'] = null;
                $data['end_time'] = null;
            } 
            else if ($request->has('schedule')) {
                $data['schedule'] = $request->schedule;
                $data['is_open_24h'] = false;
                
                $firstOpenDay = collect($request->schedule)->firstWhere('is_open', true);
                if ($firstOpenDay) {
                    $data['start_time'] = $firstOpenDay['start'];
                    $data['end_time'] = $firstOpenDay['end'];
                }
            }
            else {
                $data['is_open_24h'] = $request->boolean('is_open_24h', false);
                $data['start_time'] = $request->start_time;
                $data['end_time'] = $request->end_time;
                
                if (!$data['is_open_24h'] && $request->start_time && $request->end_time) {
                    $schedule = [];
                    foreach (Service::DAYS as $day => $label) {
                        $schedule[$day] = [
                            'is_open' => true,
                            'start' => $request->start_time,
                            'end' => $request->end_time
                        ];
                    }
                    $data['schedule'] = $schedule;
                }
            }

            $service = Service::create($data);
            $service->load('entreprise', 'domaine');

            return response()->json([
                'message' => 'Service créé avec succès',
                'service' => $this->formatServiceResponse($service)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur création service:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors de la création du service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)   {
        $service = Service::with('entreprise', 'domaine')->where('is_always_open', true)->find($id);

        if (!$service) {
            return response()->json(['message' => 'Service non trouvé'], 404);
        }

        return response()->json($this->formatServiceResponse($service));
    }

    public function toggleVisibility(Request $request, $id) {
            $user = Auth::user();
            
            $service = Service::where('id', $id)
                ->where('prestataire_id', $user->id)
                ->first();
                
            if (!$service) {
                return response()->json(['message' => 'Service non trouvé'], 404);
            }
            
            $service->is_visibility = $request->boolean('is_visibility', !$service->is_visibility);
            $service->save();
            
            return response()->json([
                'message' => 'Visibilité mise à jour',
                'is_visibility' => $service->is_visibility
            ]);
    }

    public function update(Request $request, $id){
        $user = Auth::user();

        $service = Service::where('id', $id)
            ->where('prestataire_id', $user->id)
            ->first();

        if (!$service) {
            return response()->json(['message' => 'Service introuvable ou non autorisé'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'          => 'sometimes|string|max:255',
            'price'         => 'nullable|numeric',
            'price_promo'   => 'nullable|numeric|lt:price',
            'is_price_on_request' => 'boolean',
            'has_promo'     => 'boolean',
            'promo_start_date' => 'nullable|date',
            'promo_end_date'   => 'nullable|date|after:promo_start_date',
            'descriptions'  => 'nullable|string',
            'is_always_open' => 'nullable|boolean',
            'is_always_open'  => true,
            'schedule'      => 'nullable|array',
            'schedule.*.is_open' => 'boolean',
            'schedule.*.start' => 'required_if:schedule.*.is_open,true|nullable|date_format:H:i',
            'schedule.*.end'   => 'required_if:schedule.*.is_open,true|nullable|date_format:H:i|after:schedule.*.start',
            'medias.*'      => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'deleted_medias.*' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Gestion des médias
            $medias = $service->medias ?? [];
            
            if ($request->has('deleted_medias')) {
                $deletedMedias = $request->input('deleted_medias');
                
                if (is_array($deletedMedias)) {
                    foreach ($deletedMedias as $mediaUrl) {
                        $this->deleteImageFromCloudinary($mediaUrl);
                        $medias = array_filter($medias, function($existingMedia) use ($mediaUrl) {
                            return $existingMedia !== $mediaUrl;
                        });
                    }
                } elseif (is_string($deletedMedias)) {
                    $this->deleteImageFromCloudinary($deletedMedias);
                    $medias = array_filter($medias, function($existingMedia) use ($deletedMedias) {
                        return $existingMedia !== $deletedMedias;
                    });
                }
                
                $medias = array_values($medias);
            }

            if ($request->hasFile('medias')) {
                foreach ($request->file('medias') as $file) {
                    $uploadedFile = $this->uploadToCloudinary($file, 'services', $user->id);
                    $medias[] = $uploadedFile;
                }
            }

            $data = $request->only([
                'name', 
                'price', 
                'price_promo', 
                'descriptions',
                'promo_start_date',
                'promo_end_date'
            ]);

            // Gestion des booléens
            if ($request->has('is_price_on_request')) {
                $data['is_price_on_request'] = $request->boolean('is_price_on_request');
                
                if ($data['is_price_on_request']) {
                    $data['price'] = null;
                    $data['price_promo'] = null;
                    $data['has_promo'] = false;
                }
            }

            if ($request->has('has_promo')) {
                $data['has_promo'] = $request->boolean('has_promo');
                
                if ($data['has_promo'] && $request->price_promo && $request->price) {
                    if ($request->price_promo >= $request->price) {
                        return response()->json([
                            'errors' => ['price_promo' => ['Le prix promotionnel doit être inférieur au prix normal']]
                        ], 422);
                    }
                }
            }

            $data['medias'] = $medias;

            // Gestion des horaires
            if ($request->has('is_always_open')) {
                $data['is_always_open'] = $request->boolean('is_always_open');
                
                if ($data['is_always_open']) {
                    $data['is_open_24h'] = true;
                    $data['schedule'] = null;
                    $data['start_time'] = null;
                    $data['end_time'] = null;
                }
            }

            if ($request->has('schedule') && !$request->boolean('is_always_open')) {
                $data['schedule'] = $request->schedule;
                $data['is_open_24h'] = false;
                                
                $firstOpenDay = collect($request->schedule)->firstWhere('is_open', true);
                if ($firstOpenDay) {
                    $data['start_time'] = $firstOpenDay['start'];
                    $data['end_time'] = $firstOpenDay['end'];
                }
            }

            $service->fill($data);
            $service->save();

            $service->load('entreprise', 'domaine');

            return response()->json([
                'message' => 'Service mis à jour avec succès',
                'service' => $this->formatServiceResponse($service)
            ]);
        } 
        catch (\Exception $e) {
            Log::error('Erreur update service:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Erreur interne',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateMobile(Request $request, $id){
        $user = Auth::user();

        $service = Service::where('id', $id)
            ->where('prestataire_id', $user->id)
            ->first();

        if (!$service) {
            return response()->json(['message' => 'Service introuvable ou non autorisé'], 404);
        }

        // Décoder manuellement le JSON si nécessaire (Content-Type: application/json)
        $input = $request->all();

        $validator = Validator::make($input, [
            'name'                => 'sometimes|string|max:255',
            'domaine_id'          => 'sometimes|exists:domaines,id',
            'price'               => 'nullable|numeric',
            'price_promo'         => 'nullable|numeric',
            'is_price_on_request' => 'sometimes|boolean',
            'has_promo'           => 'sometimes|boolean',
            'descriptions'        => 'nullable|string',
            'is_always_open'      => 'sometimes|boolean',
            'schedule'            => 'nullable|array',
            'deleted_medias'      => 'nullable|array',
            'deleted_medias.*'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Gestion des médias supprimés
            $medias = $service->medias ?? [];

            if (isset($input['deleted_medias']) && is_array($input['deleted_medias'])) {
                foreach ($input['deleted_medias'] as $mediaUrl) {
                    $this->deleteImageFromCloudinary($mediaUrl);
                    $medias = array_values(array_filter($medias, fn($m) => $m !== $mediaUrl));
                }
            }

            // Nouveaux médias uploadés (multipart)
            if ($request->hasFile('medias')) {
                foreach ($request->file('medias') as $file) {
                    $medias[] = $this->uploadToCloudinary($file, 'services', $user->id);
                }
            }

            $data = [];

            // Champs texte / numériques
            if (isset($input['name']))         $data['name']         = $input['name'];
            if (isset($input['descriptions'])) $data['descriptions'] = $input['descriptions'];
            if (isset($input['domaine_id']))   $data['domaine_id']   = (int) $input['domaine_id'];

            // Booléens — lire correctement depuis JSON
            $isPriceOnRequest = isset($input['is_price_on_request'])
                ? filter_var($input['is_price_on_request'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $service->is_price_on_request
                : $service->is_price_on_request;

            $hasPromo = isset($input['has_promo'])
                ? filter_var($input['has_promo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $service->has_promo
                : $service->has_promo;

            $isAlwaysOpen = isset($input['is_always_open'])
                ? filter_var($input['is_always_open'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $service->is_always_open
                : $service->is_always_open;

            $data['is_price_on_request'] = $isPriceOnRequest;

            if ($isPriceOnRequest) {
                $data['price']       = null;
                $data['price_promo'] = null;
                $data['has_promo']   = false;
            } else {
                if (array_key_exists('price', $input))       $data['price']       = $input['price'];
                if (array_key_exists('price_promo', $input)) $data['price_promo'] = $input['price_promo'];
                $data['has_promo'] = $hasPromo;

                // Valider cohérence prix promo
                $finalPrice      = $data['price']       ?? $service->price;
                $finalPromoPrice = $data['price_promo'] ?? $service->price_promo;
                if ($hasPromo && $finalPromoPrice !== null && $finalPrice !== null
                    && $finalPromoPrice >= $finalPrice) {
                    return response()->json([
                        'errors' => ['price_promo' => ['Le prix promotionnel doit être inférieur au prix normal']]
                    ], 422);
                }
            }

            $data['medias'] = $medias;

            // Gestion des horaires
            $data['is_always_open'] = $isAlwaysOpen;

            if ($isAlwaysOpen) {
                $data['is_open_24h'] = true;
                $data['schedule']    = null;
                $data['start_time']  = null;
                $data['end_time']    = null;
            } elseif (isset($input['schedule']) && is_array($input['schedule'])) {
                $data['schedule']    = $input['schedule'];
                $data['is_open_24h'] = false;

                $firstOpenDay = collect($input['schedule'])->first(function ($day) {
                    return filter_var($day['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN);
                });
                if ($firstOpenDay) {
                    $data['start_time'] = $firstOpenDay['start'] ?? null;
                    $data['end_time']   = $firstOpenDay['end']   ?? null;
                }
            }

            $service->fill($data);
            $service->save();
            $service->load('entreprise', 'domaine');

            return response()->json([
                'message' => 'Service mis à jour avec succès',
                'service' => $this->formatServiceResponse($service)
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur update service:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Erreur interne',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id) {
        $user = Auth::user();

        $service = Service::where('id', $id)
            ->where('prestataire_id', $user->id)
            ->first();

        if (!$service) {
            return response()->json(['message' => 'Service introuvable ou non autorisé'], 404);
        }

        try {
            if ($service->medias) {
                foreach ($service->medias as $media) {
                    $this->deleteImageFromCloudinary($media);
                }
            }

            $service->delete();
            return response()->json(['message' => 'Service supprimé']);
        }
        catch (\Exception $e) {
            Log::error('Erreur suppression service:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    private function uploadToCloudinary($file, $folder, $subfolder = null) {
        if (!$file || !$file->isValid()) {
            throw new \Exception('Fichier invalide pour upload');
        }

        $folderPath = $subfolder
            ? "{$folder}/{$subfolder}"
            : $folder;

        try {
            $result = (new UploadApi())->upload(
                $file->getRealPath(),
                [
                    'folder' => $folderPath,
                    'resource_type' => 'auto',
                ]
            );

            return $result['secure_url'] ?? null;
        } 
        catch (\Exception $e) {
            Log::error('Erreur upload Cloudinary:', ['error' => $e->getMessage()]);
            throw new \Exception('Impossible d\'uploader le fichier sur Cloudinary');
        }
    }

    private function deleteImageFromCloudinary($url) {
        try {
            if (empty($url)) {
                return;
            }

            $publicId = $this->extractPublicIdFromUrl($url);
            
            if ($publicId) {
                Log::info('Tentative de suppression Cloudinary:', ['public_id' => $publicId]);
                $result = Cloudinary::destroy($publicId);
                Log::info('Résultat suppression Cloudinary:', ['result' => $result]);
            }
        } 
        catch (\Exception $e) {
            Log::error('Erreur suppression Cloudinary:', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function extractPublicIdFromUrl($url) {
        try {
            $pattern = '/\/v\d+\/(.+)\./';
            preg_match($pattern, $url, $matches);
            
            if (isset($matches[1])) {
                return $matches[1];
            }
            
            $pattern2 = '/\/upload\/(.+)\./';
            preg_match($pattern2, $url, $matches2);
            
            return $matches2[1] ?? null;
        } catch (\Exception $e) {
            Log::error('Erreur extraction public ID:', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
