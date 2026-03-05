<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Cloudinary\Cloudinary;
use Exception;
use Illuminate\Support\Str;
use Pusher\Pusher;

class MessageController extends Controller
{
    private $pusher = null;
    private $pusherEnabled = false;

    public function __construct()
    {
        $this->initPusher();
    }

    private function initPusher()
    {
        try {
            if (env('PUSHER_APP_ID') && env('PUSHER_APP_KEY') && env('PUSHER_APP_SECRET')) {
                $this->pusher = new Pusher(
                    env('PUSHER_APP_KEY'),
                    env('PUSHER_APP_SECRET'),
                    env('PUSHER_APP_ID'),
                    [
                        'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
                        'useTLS' => true,
                        'timeout' => 5,
                    ]
                );
                $this->pusherEnabled = true;
            }
        } catch (\Exception $e) {
            Log::warning('Pusher non disponible: ' . $e->getMessage());
            $this->pusherEnabled = false;
        }
    }

    private function triggerPusher($channel, $event, $data)
    {
        if (!$this->pusherEnabled || !$this->pusher) {
            return false;
        }

        try {
            $this->pusher->trigger($channel, $event, $data);
            return true;
        } catch (\Exception $e) {
            Log::warning('Erreur Pusher: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Démarrer une conversation avec un utilisateur
     */
    public function startConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        $receiverId = $request->receiver_id;

        // Ne pas démarrer une conversation avec soi-même
        if ($userId == $receiverId) {
            return response()->json(['message' => 'Vous ne pouvez pas démarrer une conversation avec vous-même'], 422);
        }

        // Vérifier si une conversation existe déjà
        $conversation = Conversation::where(function($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $userId)->where('user_two_id', $receiverId);
            })
            ->orWhere(function($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $receiverId)->where('user_two_id', $userId);
            })
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $userId,
                'user_two_id' => $receiverId
            ]);
        }

        // Charger les relations
        $conversation->load(['userOne', 'userTwo']);

        return response()->json($conversation);
    }

    /**
     * Démarrer une conversation pour un service
     */
    public function startServiceConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'message' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = Auth::id();
        $serviceId = $request->service_id;

        // Récupérer le service avec son entreprise et prestataire
        $service = Service::with('entreprise.prestataire')->find($serviceId);
        
        if (!$service || !$service->entreprise) {
            return response()->json(['message' => 'Service introuvable'], 404);
        }

        $receiverId = $service->entreprise->prestataire_id;

        // Ne pas démarrer une conversation avec soi-même
        if ($userId == $receiverId) {
            return response()->json(['message' => 'Vous ne pouvez pas démarrer une conversation avec vous-même'], 422);
        }

        // Vérifier si une conversation existe déjà
        $conversation = Conversation::where(function($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $userId)->where('user_two_id', $receiverId);
            })
            ->orWhere(function($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $receiverId)->where('user_two_id', $userId);
            })
            ->first();

        if (!$conversation) {
            // Créer une nouvelle conversation
            $conversation = Conversation::create([
                'user_one_id' => $userId,
                'user_two_id' => $receiverId,
                'service_id' => $serviceId,
                'service_name' => $service->name,
                'entreprise_name' => $service->entreprise->name
            ]);
        }

        // Si un message est fourni, l'envoyer
        if ($request->filled('message')) {
            return $this->sendMessageWithData($conversation->id, [
                'type' => 'text',
                'content' => $request->message
            ]);
        }

        // Charger les relations
        $conversation->load(['userOne', 'userTwo', 'service']);

        return response()->json($conversation);
    }

    /**
     * Envoyer un message avec ID de conversation dans l'URL
     */
    public function sendMessage(Request $request, $conversationId)
    {
        return $this->sendMessageWithData($conversationId, $request->all(), $request);
    }

    /**
     * Méthode interne pour envoyer un message
     */
    private function sendMessageWithData($conversationId, $data, $request = null)
    {
        $validator = Validator::make($data, [
            'type' => 'required|in:text,image,video,vocal',
            'content' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'temporary_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conv = Conversation::find($conversationId);
        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $filePath = null;
        $fileUrl = null;
        
        try {
            DB::beginTransaction();

            // Gestion des fichiers
            if ($request && $request->hasFile('file')) {
                $filePath = $this->uploadFile($request->file('file'), $conv->id, $data['type']);
                $fileUrl = $this->getFileUrl($filePath);
            }

            // Contenu par défaut si vide
            $content = $data['content'] ?? null;
            if (!$content && $filePath) {
                $content = $this->getDefaultContent($data['type']);
            }

            // Créer le message
            $message = Message::create([
                'conversation_id' => $conv->id,
                'sender_id' => $userId,
                'content' => $content,
                'type' => $data['type'],
                'file_path' => $filePath,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'temporary_id' => $data['temporary_id'] ?? null
            ]);

            // Mettre à jour le timestamp de la conversation
            $conv->touch();
            
            // Charger les relations
            $message->load('sender');
            
            // Ajouter l'URL du fichier
            $messageData = $message->toArray();
            $messageData['file_url'] = $fileUrl ?: $message->file_url;

            DB::commit();

            // Notifier via Pusher (non bloquant)
            $receiverId = $conv->user_one_id === $userId ? $conv->user_two_id : $conv->user_one_id;
            
            if ($receiverId) {
                $this->triggerPusher('private-user.' . $receiverId, 'new-message', [
                    'conversation_id' => $conv->id,
                    'message' => $messageData,
                    'sender_id' => $userId,
                    'sender_name' => Auth::user()->name
                ]);
            }

            $this->triggerPusher('private-conversation.' . $conv->id, 'message-sent', [
                'message' => $messageData,
                'sender_id' => $userId
            ]);

            return response()->json($messageData, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            if ($filePath) {
                $this->deleteFile($filePath);
            }
            Log::error('Erreur envoi message: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Récupérer les messages d'une conversation
     */
    public function getMessages($conversationId)
    {
        $conv = Conversation::with(['messages.sender', 'service'])
            ->find($conversationId);

        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Marquer les messages comme lus
        Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json($conv);
    }

    /**
     * Récupérer toutes les conversations de l'utilisateur
     */
    public function myConversations()
    {
        $userId = Auth::id();
        
        $conversations = Conversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with(['messages' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(1);
            }, 'messages.sender', 'service'])
            ->with(['userOne', 'userTwo'])
            ->latest('updated_at')
            ->get()
            ->map(function ($conv) use ($userId) {
                // Déterminer l'autre utilisateur
                $conv->other_user = $conv->user_one_id === $userId ? $conv->userTwo : $conv->userOne;
                
                // Compter les messages non lus
                $conv->unread_count = Message::where('conversation_id', $conv->id)
                    ->where('sender_id', '!=', $userId)
                    ->whereNull('read_at')
                    ->count();
                
                return $conv;
            });

        return response()->json($conversations);
    }

    /**
     * Marquer les messages comme lus
     */
    public function markAsRead($conversationId)
    {
        $conv = Conversation::find($conversationId);
        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $updated = Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Notifier l'expéditeur
        if ($updated > 0) {
            $otherUserId = $conv->user_one_id === $userId ? $conv->user_two_id : $conv->user_one_id;
            
            if ($otherUserId) {
                $this->triggerPusher('private-user.' . $otherUserId, 'messages-read', [
                    'conversation_id' => $conv->id,
                    'user_id' => $userId
                ]);
            }
        }

        return response()->json(['message' => 'Messages marqués lus', 'count' => $updated]);
    }

    /**
     * Indicateur de frappe
     */
    public function typingIndicator(Request $request, $conversationId)
    {
        $validator = Validator::make($request->all(), [
            'is_typing' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conv = Conversation::find($conversationId);
        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $receiverId = $conv->user_one_id === $userId ? $conv->user_two_id : $conv->user_one_id;

        if ($receiverId) {
            $this->triggerPusher('private-user.' . $receiverId, 'typing-indicator', [
                'conversation_id' => $conv->id,
                'user_id' => $userId,
                'is_typing' => $request->is_typing
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Indicateur d'enregistrement audio
     */
    public function recordingIndicator(Request $request, $conversationId)
    {
        $validator = Validator::make($request->all(), [
            'is_recording' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conv = Conversation::find($conversationId);
        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $receiverId = $conv->user_one_id === $userId ? $conv->user_two_id : $conv->user_one_id;

        if ($receiverId) {
            $this->triggerPusher('private-user.' . $receiverId, 'recording-indicator', [
                'conversation_id' => $conv->id,
                'user_id' => $userId,
                'is_recording' => $request->is_recording
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mettre à jour le statut en ligne
     */
    public function updateOnlineStatus()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        try {
            $user->last_seen_at = now();
            $user->save();

            // Notifier les contacts
            $conversations = Conversation::where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id)
                ->get();

            foreach ($conversations as $conv) {
                $otherUserId = $conv->user_one_id === $user->id ? $conv->user_two_id : $conv->user_one_id;
                if ($otherUserId) {
                    $this->triggerPusher('private-user.' . $otherUserId, 'user-status', [
                        'user_id' => $user->id,
                        'is_online' => true,
                        'last_seen' => $user->last_seen_at
                    ]);
                }
            }

            return response()->json([
                'message' => 'Statut mis à jour',
                'last_seen_at' => $user->last_seen_at
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur updateOnlineStatus: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur de mise à jour'], 500);
        }
    }

    /**
     * Vérifier le statut en ligne d'un utilisateur
     */
    public function checkOnlineStatus($userId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        try {
            $isOnline = false;
            if ($user->last_seen_at) {
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
            Log::error('Erreur checkOnlineStatus: ' . $e->getMessage());
            return response()->json([
                'user_id' => $user->id,
                'is_online' => false,
                'last_seen_at' => $user->last_seen_at
            ]);
        }
    }

    /**
     * Vérifier si l'utilisateur est membre de la conversation
     */
    private function isMember(Conversation $conv, ?int $userId): bool
    {
        return $conv->user_one_id === $userId || $conv->user_two_id === $userId;
    }

    /**
     * Obtenir l'URL complète d'un fichier
     */
    private function getFileUrl($filePath)
    {
        if (!$filePath) return null;

        if (filter_var($filePath, FILTER_VALIDATE_URL)) {
            return $filePath;
        }

        return Storage::disk('public')->url($filePath);
    }

    /**
     * Uploader un fichier
     */
    private function uploadFile($file, $convId, $type)
    {
        $folderPath = "messages/{$convId}/{$type}";

        if ($this->isCloudStorageEnabled()) {
            return $this->uploadToCloudinary($file, $folderPath);
        } else {
            return $this->uploadToLocalStorage($file, $folderPath);
        }
    }

    /**
     * Vérifier si Cloudinary est activé
     */
    private function isCloudStorageEnabled()
    {
        return env('CLOUDINARY_CLOUD_NAME') && 
               env('CLOUDINARY_API_KEY') && 
               env('CLOUDINARY_API_SECRET');
    }

    /**
     * Uploader vers Cloudinary
     */
    private function uploadToCloudinary($file, $folderPath)
    {
        try {
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key' => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ],
                'url' => ['secure' => true]
            ]);

            $uploadApi = $cloudinary->uploadApi();
            
            $options = [
                'folder' => $folderPath,
                'resource_type' => 'auto',
            ];

            $result = $uploadApi->upload($file->getRealPath(), $options);
            return $result['secure_url'];
        } catch (\Exception $e) {
            Log::error('Cloudinary upload error: ' . $e->getMessage());
            throw new Exception('Erreur upload Cloudinary');
        }
    }

    /**
     * Uploader vers stockage local
     */
    private function uploadToLocalStorage($file, $folderPath)
    {
        try {
            Storage::disk('public')->makeDirectory($folderPath);
            
            $extension = $file->getClientOriginalExtension();
            $fileName = Str::uuid() . '.' . $extension;
            
            Storage::disk('public')->putFileAs($folderPath, $file, $fileName);
            
            return $folderPath . '/' . $fileName;
        } catch (\Exception $e) {
            Log::error('Erreur upload local: ' . $e->getMessage());
            throw new Exception('Erreur upload local');
        }
    }

    /**
     * Supprimer un fichier
     */
    private function deleteFile($filePath)
    {
        if (!$filePath) return;
        
        if ($this->isCloudStorageEnabled() && filter_var($filePath, FILTER_VALIDATE_URL)) {
            try {
                $pathInfo = parse_url($filePath);
                $pathParts = explode('/', $pathInfo['path']);
                $publicIdWithExtension = end($pathParts);
                $publicId = pathinfo($publicIdWithExtension, PATHINFO_FILENAME);
                
                $cloudinary = new Cloudinary([
                    'cloud' => [
                        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                        'api_key' => env('CLOUDINARY_API_KEY'),
                        'api_secret' => env('CLOUDINARY_API_SECRET'),
                    ]
                ]);
                
                $cloudinary->uploadApi()->destroy($publicId);
            } catch (\Exception $e) {
                Log::error('Erreur suppression Cloudinary: ' . $e->getMessage());
            }
        } else {
            try {
                Storage::disk('public')->delete($filePath);
            } catch (\Exception $e) {
                Log::error('Erreur suppression locale: ' . $e->getMessage());
            }
        }
    }

    /**
     * Obtenir le contenu par défaut selon le type
     */
    private function getDefaultContent($type)
    {
        return match ($type) {
            'image' => 'Image',
            'video' => 'Vidéo',
            'vocal' => 'Message vocal',
            'image' => 'Document',
            default => 'Message',
        };
    }
}