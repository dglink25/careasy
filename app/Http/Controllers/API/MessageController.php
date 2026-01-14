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
            'file'      => 'nullable|file',
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
        
        try {
            if ($request->hasFile('file')) {
                // CAS 1: Fichier classique (multipart/form-data)
                $filePath = $this->uploadFile($request->file('file'), $conv->id, $request->type);
            }
            elseif ($request->file_data) {
                // CAS 2: Fichier en base64 (JSON)
                $filePath = $this->uploadBase64File($request->file_data, $conv->id, $request->type, $request->file_name);
            }

            /* -------- création message -------- */
            DB::beginTransaction();

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
            DB::commit();

            Log::info('Message créé avec succès', [
                'message_id' => $msg->id,
                'type' => $msg->type,
                'has_file' => !is_null($filePath),
            ]);
            
            return response()->json($msg->load('sender'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            // Si fichier uploadé, on le supprime (rollback)
            if ($filePath) {
                $this->deleteFile($filePath);
            }
            Log::error('Erreur lors de l\'envoi du message', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur interne'], 500);
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
        // Créer un fichier temporaire à partir du base64
        $tmpFile = tmpfile();
        fwrite($tmpFile, base64_decode($base64Data));
        $meta = stream_get_meta_data($tmpFile);
        $tmpFilePath = $meta['uri'];
        $file = new \Illuminate\Http\File($tmpFilePath);

        $folderPath = "messages/{$convId}/{$type}";

        if ($this->isCloudStorageEnabled()) {
            return $this->uploadToCloudinary($file, $folderPath, $fileName);
        } else {
            return $this->uploadToLocalStorage($file, $folderPath);
        }
    }

    // Vérifie si Cloudinary est activé
    private function isCloudStorageEnabled() {
        return env('CLOUDINARY_CLOUD_NAME') && env('CLOUDINARY_API_KEY') && env('CLOUDINARY_API_SECRET');
    }

    // Upload vers Cloudinary
    private function uploadToCloudinary($file, $folderPath, $fileName = null) {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
            'url' => ['secure' => true],
        ]);

        try {
            $result = (new \Cloudinary\Api\Upload\UploadApi())->upload(
                $file->getRealPath(),
                [
                    'folder' => $folderPath,
                    'resource_type' => 'auto',
                    'public_id' => $fileName ? Str::slug($fileName) . '-' . uniqid() : null,
                ]
            );
            return $result['secure_url'] ?? null;
        } catch (\Exception $e) {
            Log::error('Cloudinary upload error', [
                'error' => $e->getMessage(),
                'path' => $file->getRealPath(),
            ]);
            throw new Exception('Erreur lors de l\'upload vers Cloudinary');
        }
    }

    // Upload vers stockage local
    private function uploadToLocalStorage($file, $folderPath) {
        try {
            return $file->store($folderPath, 'public');
        } catch (\Exception $e) {
            Log::error('Erreur upload local', [
                'error' => $e->getMessage(),
                'path' => $file->getRealPath(),
            ]);
            throw new Exception('Erreur lors de l\'upload local');
        }
    }

    // Méthode pour supprimer un fichier (Cloudinary ou local)
    private function deleteFile($filePath) {
        if ($this->isCloudStorageEnabled()) {
            // Supprimer du cloud
            $publicId = basename($filePath, '.' . pathinfo($filePath, PATHINFO_EXTENSION));
            try {
                (new \Cloudinary\Api\Upload\UploadApi())->destroy($publicId);
            } catch (\Exception $e) {
                Log::error('Erreur suppression Cloudinary', ['error' => $e->getMessage()]);
            }
        } else {
            // Supprimer du stockage local
            Storage::disk('public')->delete($filePath);
        }
    }

    // Contenu par défaut en fonction du type de fichier
    private function getDefaultContent($type, $filePath) {
        if (!$filePath) {
            return $type === 'text' ? 'Message texte' : 'Fichier envoyé';
        }

        return match ($type) {
            'image' => 'Image envoyée',
            'video' => 'Vidéo envoyée',
            'vocal' => 'Message vocal',
            'document' => 'Document envoyé',
            default => 'Fichier envoyé',
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
        $conv = Conversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with(['messages.sender'])
            ->latest('updated_at')
            ->get()
            ->map(function ($c) use ($userId) {
                $c->other_user = $c->user_one_id === $userId ? $c->userTwo : $c->userOne;
                $c->unread_count = $c->unreadCountFor($userId);
                return $c;
            });

        return response()->json($conv);
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
            $conv = Conversation::where(fn($q) => $q->where('user_one_id', $userId)->where('user_two_id', $recvId))
                ->orWhere(fn($q) => $q->where('user_one_id', $recvId)->where('user_two_id', $userId))
                ->first();
            if (!$conv) {
                $conv = Conversation::create(['user_one_id' => $userId, 'user_two_id' => $recvId]);
            }
            return response()->json($conv);
        }

        return response()->json(['message' => 'Receiver required'], 422);
    }

    private function isMember(Conversation $conv, ?int $userId): bool {
        return $conv->user_one_id === $userId || $conv->user_two_id === $userId;
    }

    /**
     * Mettre à jour mon statut en ligne
     */
    public function updateOnlineStatus() {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $user->last_seen_at = now();
        $user->save();

        return response()->json([
            'message' => 'Statut mis à jour',
            'last_seen_at' => $user->last_seen_at
        ]);
    }

    /**
     * Vérifier le statut en ligne d'un utilisateur
     */
    public function checkOnlineStatus($userId){
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        // Un utilisateur est considéré "en ligne" si vu dans les 5 dernières minutes
        $isOnline = $user->last_seen_at && 
                    $user->last_seen_at->diffInMinutes(now()) < 5;

        return response()->json([
            'user_id' => $user->id,
            'is_online' => $isOnline,
            'last_seen_at' => $user->last_seen_at
        ]);
    }

}
