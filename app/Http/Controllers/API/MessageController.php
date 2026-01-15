<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Exception;
use Illuminate\Support\Str;

class MessageController extends Controller{
    public function getMessages($convId) {
        $conv = Conversation::with(['messages.sender'])
            ->find($convId);

        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        // Autorisation : faire partie de la conversation
        if (!$this->isMember($conv, Auth::id())) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Formatage correct des messages
        $conv->messages->transform(function ($message) {
            return [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_id' => $message->sender_id,
                'sender' => $message->sender,
                'type' => $message->type,
                'content' => $message->content,
                'file_path' => $message->file_path,
                'file_url' => $this->getFileUrl($message->file_path, $message->type),
                'latitude' => $message->latitude,
                'longitude' => $message->longitude,
                'read_at' => $message->read_at,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at,
            ];
        });

        return response()->json($conv);
    }

    public function sendMessage(Request $request, $convId)  {
        $conv = Conversation::find($convId);
        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        /* -------- validation -------- */
        $rules = [
            'type'      => 'required|in:text,image,video,vocal,document',
            'content'   => 'nullable|string',
            // Accepter soit un fichier classique, soit base64
            'file'      => 'nullable|file|max:10240', // 10MB max
            'file_data' => 'nullable|string',
            'file_name' => 'nullable|string',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Vérifier qu'on a au moins du contenu ou un fichier
        if ($request->type !== 'text' && !$request->hasFile('file') && !$request->file_data) {
            return response()->json(['message' => 'Fichier requis pour ce type de message'], 422);
        }

        /* -------- upload fichier -------- */
        $filePath = null;
        $fileUrl = null;
        
        try {
            DB::beginTransaction();

            if ($request->hasFile('file')) {
                // CAS 1: Fichier classique (multipart/form-data)
                $filePath = $this->uploadFile($request->file('file'), $conv->id, $request->type);
                $fileUrl = $this->getFileUrl($filePath, $request->type);
            }
            elseif ($request->file_data) {
                // CAS 2: Fichier en base64 (JSON)
                $filePath = $this->uploadBase64File($request->file_data, $conv->id, $request->type, $request->file_name);
                $fileUrl = $this->getFileUrl($filePath, $request->type);
            }

            /* -------- création message -------- */
            $content = $request->input('content', $this->getDefaultContent($request->type, $filePath));

            $msg = Message::create([
                'conversation_id' => $conv->id,
                'sender_id'       => $userId,
                'content'         => $content,
                'type'            => $request->type,
                'file_path'       => $filePath,
                'latitude'        => $request->latitude,
                'longitude'       => $request->longitude,
            ]);

            $conv->touch(); // mise à jour du timestamp
            
            // Charger la relation sender
            $msg->load('sender');
            
            // Ajouter l'URL du fichier à la réponse
            $msgData = $msg->toArray();
            $msgData['file_url'] = $fileUrl;

            DB::commit();

            Log::info('Message créé avec succès', [
                'message_id' => $msg->id,
                'type' => $msg->type,
                'has_file' => !is_null($filePath),
            ]);
            
            return response()->json($msgData, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            // Si fichier uploadé, on le supprime (rollback)
            if ($filePath) {
                $this->deleteFile($filePath);
            }
            Log::error('Erreur lors de l\'envoi du message', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur interne: ' . $e->getMessage()], 500);
        }
    }

    // Méthode pour obtenir l'URL complète du fichier
    private function getFileUrl($filePath, $type) {
        if (!$filePath) {
            return null;
        }

        if ($this->isCloudStorageEnabled()) {
            // Cloudinary retourne déjà une URL complète
            return $filePath;
        } else {
            // Stockage local
            return Storage::disk('public')->url($filePath);
        }
    }

    // Méthode pour uploader un fichier vers Cloudinary ou local
    private function uploadFile($file, $convId, $type){
        // On détermine le répertoire du type de fichier
        $folderPath = "messages/{$convId}/{$type}";

        if ($this->isCloudStorageEnabled()) {
            return $this->uploadToCloudinary($file, $folderPath);
        } else {
            return $this->uploadToLocalStorage($file, $folderPath);
        }
    }

    // Méthode pour uploader un fichier en base64 vers Cloudinary ou local
    private function uploadBase64File($base64Data, $convId, $type, $fileName){
        // Extraire les données base64
        if (strpos($base64Data, 'base64,') !== false) {
            $base64Data = explode('base64,', $base64Data)[1];
        }
        
        // Décoder les données base64
        $fileData = base64_decode($base64Data);
        
        // Créer un fichier temporaire
        $tmpFilePath = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($tmpFilePath, $fileData);
        
        $file = new \Illuminate\Http\File($tmpFilePath);
        $folderPath = "messages/{$convId}/{$type}";

        try {
            if ($this->isCloudStorageEnabled()) {
                return $this->uploadToCloudinary($file, $folderPath, $fileName);
            } else {
                return $this->uploadToLocalStorage($file, $folderPath);
            }
        } finally {
            // Nettoyer le fichier temporaire
            @unlink($tmpFilePath);
        }
    }

    // Vérifie si Cloudinary est activé
    private function isCloudStorageEnabled() {
        return env('CLOUDINARY_CLOUD_NAME') && env('CLOUDINARY_API_KEY') && env('CLOUDINARY_API_SECRET');
    }

    // Upload vers Cloudinary
    private function uploadToCloudinary($file, $folderPath, $fileName = null) {
        try {
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key'    => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ],
                'url' => [
                    'secure' => true
                ]
            ]);

            $uploadApi = $cloudinary->uploadApi();
            
            $options = [
                'folder' => $folderPath,
                'resource_type' => 'auto',
            ];
            
            if ($fileName) {
                $options['public_id'] = Str::slug(pathinfo($fileName, PATHINFO_FILENAME)) . '-' . uniqid();
            }

            $result = $uploadApi->upload($file->getRealPath(), $options);
            
            return $result['secure_url'];
        } catch (\Exception $e) {
            Log::error('Cloudinary upload error', [
                'error' => $e->getMessage(),
                'path' => $file->getRealPath(),
            ]);
            throw new Exception('Erreur lors de l\'upload vers Cloudinary: ' . $e->getMessage());
        }
    }

    // Upload vers stockage local
    private function uploadToLocalStorage($file, $folderPath) {
        try {
            // Créer le dossier s'il n'existe pas
            Storage::disk('public')->makeDirectory($folderPath);
            
            // Générer un nom de fichier unique
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $fullPath = $folderPath . '/' . $fileName;
            
            // Stocker le fichier
            Storage::disk('public')->putFileAs($folderPath, $file, $fileName);
            
            return $fullPath;
        } catch (\Exception $e) {
            Log::error('Erreur upload local', [
                'error' => $e->getMessage(),
                'path' => $file->getRealPath(),
            ]);
            throw new Exception('Erreur lors de l\'upload local: ' . $e->getMessage());
        }
    }

    // Méthode pour supprimer un fichier (Cloudinary ou local)
    private function deleteFile($filePath) {
        if (!$filePath) return;
        
        if ($this->isCloudStorageEnabled()) {
            // Pour Cloudinary, extraire le public_id de l'URL
            try {
                $pathInfo = parse_url($filePath);
                $pathParts = explode('/', $pathInfo['path']);
                $publicIdWithExtension = end($pathParts);
                $publicId = pathinfo($publicIdWithExtension, PATHINFO_FILENAME);
                
                $cloudinary = new Cloudinary([
                    'cloud' => [
                        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                        'api_key'    => env('CLOUDINARY_API_KEY'),
                        'api_secret' => env('CLOUDINARY_API_SECRET'),
                    ]
                ]);
                
                $uploadApi = $cloudinary->uploadApi();
                $uploadApi->destroy($publicId);
            } catch (\Exception $e) {
                Log::error('Erreur suppression Cloudinary', ['error' => $e->getMessage()]);
            }
        } else {
            // Supprimer du stockage local
            try {
                Storage::disk('public')->delete($filePath);
            } catch (\Exception $e) {
                Log::error('Erreur suppression locale', ['error' => $e->getMessage()]);
            }
        }
    }

    // Contenu par défaut en fonction du type de fichier
    private function getDefaultContent($type, $filePath) {
        return match ($type) {
            'image' => '🖼️ Image',
            'video' => '🎥 Vidéo',
            'vocal' => '🎤 Message vocal',
            'document' => '📄 Document',
            default => '📝 Message',
        };
    }

    public function markAsRead($convId) {
        $conv = Conversation::find($convId);
        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }
        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $updated = Message::where('conversation_id', $convId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Messages marqués lus', 'count' => $updated]);
    }

    public function myConversations() {
        $userId = Auth::id();
        
        $conversations = Conversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with(['messages' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }, 'messages.sender'])
            ->with(['userOne', 'userTwo'])
            ->latest('updated_at')
            ->get()
            ->map(function ($conv) use ($userId) {
                $conv->other_user = $conv->user_one_id === $userId ? $conv->userTwo : $conv->userOne;
                $conv->unread_count = $conv->messages()
                    ->where('sender_id', '!=', $userId)
                    ->whereNull('read_at')
                    ->count();
                    
                // Ajouter l'URL des fichiers pour le dernier message
                if ($conv->messages->count() > 0) {
                    $lastMessage = $conv->messages->first();
                    $lastMessage->file_url = $this->getFileUrl($lastMessage->file_path, $lastMessage->type);
                }
                
                return $conv;
            });

        return response()->json($conversations);
    }

    public function startConversation(Request $request) {
        $request->validate(['receiver_id' => 'nullable|exists:users,id']);
        $userId = Auth::id();
        $recvId = $request->receiver_id;

        // anonyme
        if (!$userId && $recvId) {
            $conv = Conversation::create(['user_one_id' => $recvId, 'user_two_id' => null]);
            return response()->json($conv, 201);
        }

        // authentifié
        if ($userId && $recvId) {
            $conv = Conversation::where(function($q) use ($userId, $recvId) {
                    $q->where('user_one_id', $userId)->where('user_two_id', $recvId);
                })
                ->orWhere(function($q) use ($userId, $recvId) {
                    $q->where('user_one_id', $recvId)->where('user_two_id', $userId);
                })
                ->first();
                
            if (!$conv) {
                $conv = Conversation::create([
                    'user_one_id' => $userId,
                    'user_two_id' => $recvId
                ]);
            }
            return response()->json($conv);
        }

        return response()->json(['message' => 'Receiver required'], 422);
    }

    private function isMember(Conversation $conv, ?int $userId): bool {
        return $conv->user_one_id === $userId || $conv->user_two_id === $userId;
    }

    public function checkOnlineStatus($userId){
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        try {
            // Un utilisateur est considéré "en ligne" si vu dans les 5 dernières minutes
            $isOnline = false;
            
            if ($user->last_seen_at) {
                // Convertir en objet Carbon si c'est une chaîne
                $lastSeen = is_string($user->last_seen_at) 
                    ? \Carbon\Carbon::parse($user->last_seen_at)
                    : $user->last_seen_at;
                    
                $isOnline = $lastSeen->diffInMinutes(now()) < 5;
            }

            return response()->json([
                'user_id' => $user->id,
                'is_online' => $isOnline,
                'last_seen_at' => $user->last_seen_at
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur checkOnlineStatus', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'last_seen_at' => $user->last_seen_at,
                'type' => gettype($user->last_seen_at)
            ]);
            
            return response()->json([
                'user_id' => $user->id,
                'is_online' => false,
                'last_seen_at' => $user->last_seen_at,
                'error' => 'Erreur de calcul du statut'
            ], 200); // Retourner 200 avec is_online = false plutôt que 500
        }
    }

    public function updateOnlineStatus() {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        try {
            $user->last_seen_at = now();
            $user->save();

            return response()->json([
                'message' => 'Statut mis à jour',
                'last_seen_at' => $user->last_seen_at
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur updateOnlineStatus', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            return response()->json(['message' => 'Erreur de mise à jour'], 500);
        }
    }
}