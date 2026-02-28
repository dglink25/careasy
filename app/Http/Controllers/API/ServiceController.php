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

class ServiceController extends Controller{
    public function __construct(){
        // Configuration Cloudinary globale
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

    /**
     * Services de l'entreprise du prestataire
     */
    public function mine() {
        $user = Auth::user();

        $services = Service::with('entreprise', 'domaine')
            ->where('prestataire_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($services);
    }

    public function index() {
        return Service::with('entreprise', 'domaine')
            ->whereHas('entreprise', fn($q) => $q->where('status', 'validated'))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(Request $request) {
        Log::info('Création service - Données reçues:', $request->all());

        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'entreprise_id' => 'required|exists:entreprises,id',
            'domaine_id'    => 'required|exists:domaines,id',
            'name'          => 'required|string|max:255',
            'price'         => 'nullable|numeric',
            'descriptions'  => 'nullable|string',
            'start_time'    => 'nullable|date_format:H:i',
            'end_time'      => 'nullable|date_format:H:i',
            'is_open_24h'   => 'nullable|boolean',
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
            // Upload des médias sur Cloudinary
            $medias = [];
            if ($request->hasFile('medias')) {
                foreach ($request->file('medias') as $file) {
                    $medias[] = $this->uploadToCloudinary($file, 'services', $user->id);
                }
            }

            $service = Service::create([
                'entreprise_id' => $entreprise->id,
                'prestataire_id'=> $user->id,
                'domaine_id'    => $request->domaine_id,
                'name'          => $request->name,
                'price'         => $request->price,
                'descriptions'  => $request->descriptions ?? '',
                'start_time'    => $request->start_time,
                'end_time'      => $request->end_time,
                'is_open_24h'   => $request->is_open_24h ?? false,
                'medias'        => $medias,
            ]);

            $service->load('entreprise', 'domaine');

            return response()->json([
                'message' => 'Service créé avec succès',
                'service' => $service
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur création service:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors de la création du service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id) {
        $service = Service::with('entreprise', 'domaine')->find($id);

        if (!$service) {
            return response()->json(['message' => 'Service non trouvé'], 404);
        }

        return response()->json($service);
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
            $service->delete();
            return response()->json(['message' => 'Service supprimé']);
        } catch (\Exception $e) {
            Log::error('Erreur suppression service:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur interne'], 500);
        }
    }

    /**
     * Upload fichier sur Cloudinary avec gestion des dossiers
     */
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
        } catch (\Exception $e) {
            Log::error('Erreur upload Cloudinary:', ['error' => $e->getMessage()]);
            throw new \Exception('Impossible d\'uploader le fichier sur Cloudinary');
        }
    }


    public function update(Request $request, $id) {
        $user = Auth::user();

        $service = Service::where('id', $id)
            ->where('prestataire_id', $user->id)
            ->first();

        if (!$service) {
            return response()->json(['message' => 'Service introuvable ou non autorisé'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'         => 'sometimes|string|max:255',
            'price'        => 'nullable|numeric',
            'descriptions' => 'nullable|string',
            'start_time'   => 'nullable',
            'end_time'     => 'nullable',
            'is_open_24h'  => 'nullable|boolean',
            'medias.*'     => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'deleted_medias.*' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Récupérer les médias actuels
            $medias = $service->medias ?? [];
            
            // SUPPRIMER LES IMAGES MARQUÉES POUR SUPPRESSION
            if ($request->has('deleted_medias')) {
                $deletedMedias = $request->input('deleted_medias');
                
                // Log pour déboguer
                Log::info('Images à supprimer:', ['deleted_medias' => $deletedMedias]);
                
                // Traiter selon le type de données reçues
                if (is_array($deletedMedias)) {
                    foreach ($deletedMedias as $mediaUrl) {
                        $this->deleteImageFromCloudinary($mediaUrl);
                        // Retirer du tableau des médias
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
                
                // Réindexer le tableau
                $medias = array_values($medias);
            }

            // AJOUTER LES NOUVEAUX MÉDIAS
            if ($request->hasFile('medias')) {
                foreach ($request->file('medias') as $file) {
                    $uploadedFile = $this->uploadToCloudinary($file, 'services', $user->id);
                    $medias[] = $uploadedFile;
                }
            }

            // Mettre à jour le champ medias
            $service->medias = $medias;

            // Mettre à jour les autres champs
            $service->fill($request->only([
                'name', 'price', 'descriptions', 'start_time', 'end_time', 'is_open_24h'
            ]));
            
            $service->save();

            $service->load('entreprise', 'domaine');

            return response()->json([
                'message' => 'Service mis à jour avec succès',
                'service' => $service
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

    private function deleteImageFromCloudinary($url)  {
        try {
            if (empty($url)) {
                return;
            }

            // Extraire l'ID public de l'URL Cloudinary
            $publicId = $this->extractPublicIdFromUrl($url);
            
            if ($publicId) {
                Log::info('Tentative de suppression Cloudinary:', ['public_id' => $publicId]);
                
                // Méthode 1: Via le facade
                $result = Cloudinary::destroy($publicId);
                
                // Méthode 2: Alternative si la première ne fonctionne pas
                // $result = cloudinary()->destroy($publicId);
                
                Log::info('Résultat suppression Cloudinary:', ['result' => $result]);
            }
        } catch (\Exception $e) {
            Log::error('Erreur suppression Cloudinary:', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            // Ne pas bloquer la requête si la suppression échoue
        }
    }

    private function extractPublicIdFromUrl($url){
        try {
            // Format: https://res.cloudinary.com/demo/image/upload/v1234567890/folder/abc123.jpg
            $pattern = '/\/v\d+\/(.+)\./';
            preg_match($pattern, $url, $matches);
            
            if (isset($matches[1])) {
                return $matches[1];
            }
            
            // Format alternatif: https://res.cloudinary.com/demo/image/upload/folder/abc123.jpg
            $pattern2 = '/\/upload\/(.+)\./';
            preg_match($pattern2, $url, $matches2);
            
            return $matches2[1] ?? null;
        } catch (\Exception $e) {
            Log::error('Erreur extraction public ID:', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
