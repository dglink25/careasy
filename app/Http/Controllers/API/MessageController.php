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

class MessageController extends Controller{
    private $pusher = null;
    private $pusherEnabled = false;

    public function __construct() {
        $this->initPusher();
    }

    private function initPusher() {
        try {
            if (env('PUSHER_APP_ID') && env('PUSHER_APP_KEY') && env('PUSHER_APP_SECRET')) {
                $this->pusher = new Pusher(
                    env('PUSHER_APP_KEY'),
                    env('PUSHER_APP_SECRET'),
                    env('PUSHER_APP_ID'),
                    [
                        'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
                        'useTLS' => true,
                        'timeout' => 30,
                        'debug' => true
                    ]
                );
                $this->pusherEnabled = true;
                Log::info('Pusher initialisé avec succès');
            }
        } catch (\Exception $e) {
            Log::error('Pusher non disponible: ' . $e->getMessage());
            $this->pusherEnabled = false;
        }
    }

    private function triggerPusher($channel, $event, $data) {
        if (!$this->pusherEnabled || !$this->pusher) {
            Log::warning('Pusher désactivé, événement non envoyé', [
                'channel' => $channel, 'event' => $event
            ]);
            return false;
        }
        try {
            $result = $this->pusher->trigger($channel, $event, $data);
            Log::info('Événement Pusher envoyé', [
                'channel' => $channel, 'event' => $event,
                'result' => $result ? 'succès' : 'échec'
            ]);
            return $result;
        } catch (\Exception $e) {
            Log::error('Erreur Pusher: ' . $e->getMessage(), [
                'channel' => $channel, 'event' => $event
            ]);
            return false;
        }
    }

    public function startConversation(Request $request) {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId     = Auth::id();
        $receiverId = $request->receiver_id;

        if ($userId == $receiverId) {
            return response()->json(['message' => 'Vous ne pouvez pas démarrer une conversation avec vous-même'], 422);
        }

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

        $conversation->load(['userOne', 'userTwo']);
        return response()->json($conversation);
    }

    public function startServiceConversation(Request $request) {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'message'    => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId    = Auth::id();
        $serviceId = $request->service_id;
        $service   = Service::with('entreprise.prestataire')->find($serviceId);

        if (!$service || !$service->entreprise) {
            return response()->json(['message' => 'Service introuvable'], 404);
        }

        $receiverId = $service->entreprise->prestataire_id;

        if ($userId == $receiverId) {
            return response()->json(['message' => 'Vous ne pouvez pas démarrer une conversation avec vous-même'], 422);
        }

        $conversation = Conversation::where(function($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $userId)->where('user_two_id', $receiverId);
            })
            ->orWhere(function($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $receiverId)->where('user_two_id', $userId);
            })
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id'    => $userId,
                'user_two_id'    => $receiverId,
                'service_id'     => $serviceId,
                'service_name'   => $service->name,
                'entreprise_name'=> $service->entreprise->name
            ]);
        }

        if ($request->filled('message')) {
            $messageRequest = new Request([
                'type'    => 'text',
                'content' => $request->message
            ]);
            return $this->sendMessageWithData($conversation->id, $messageRequest);
        }

        $conversation->load(['userOne', 'userTwo', 'service']);
        return response()->json($conversation);
    }

public function sendMessage(Request $request, $conversationId) {
    return $this->sendMessageWithData($conversationId, $request);
}

    private function sendMessageWithData($conversationId, Request $request) {
        $validator = Validator::make($request->all(), [
            'type'         => 'required|in:text,image,video,vocal,document',
            'content'      => 'nullable|string',
            'file'         => 'nullable|file|max:20480',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
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
        $fileUrl  = null;

        try {
            DB::beginTransaction();

            if ($request->hasFile('file')) {
                $filePath = $this->uploadFile($request->file('file'), $conv->id, $request->type);
                $fileUrl  = $this->getFileUrl($filePath);
            }

            $content = $request->input('content');
            if (!$content && $filePath) {
                $content = $this->getDefaultContent($request->type);
            }

            $message = Message::create([
                'conversation_id' => $conv->id,
                'sender_id'       => $userId,
                'content'         => $content,
                'type'            => $request->type,
                'file_path'       => $filePath,
                'latitude'        => $request->latitude,
                'longitude'       => $request->longitude,
                'temporary_id'    => $request->temporary_id,
                'reply_to_id'     => $request->reply_to_id ?? null
            ]);

            $conv->touch();
            $message->load(['sender', 'replyTo']);
            $message->load('sender');

            $messageData            = $message->toArray();
            $messageData['file_url'] = $fileUrl ?: $message->file_url;

            DB::commit();

            $receiverId = $conv->user_one_id === $userId
                ? $conv->user_two_id
                : $conv->user_one_id;

            if ($receiverId) {
                $this->triggerPusher('private-user.' . $receiverId, 'new-message', [
                    'conversation_id' => $conv->id,
                    'message'         => $messageData,
                    'sender_id'       => $userId,
                    'sender_name'     => Auth::user()->name
                ]);

                $this->triggerPusher('private-conversation.' . $conv->id, 'message-sent', [
                    'message'   => $messageData,
                    'sender_id' => $userId
                ]);

 $recipient = User::find($receiverId);
                if ($recipient && $recipient->id !== $userId) {
                    $recipient->notify(new \App\Notifications\NewMessageNotification($message));
                    Log::info('Notification de message envoyée', [
                        'recipient_id' => $recipient->id,
                        'message_id' => $message->id
                    ]);
                }
            }

            return response()->json($messageData, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            if ($filePath) $this->deleteFile($filePath);
            Log::error('Erreur envoi message: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne: ' . $e->getMessage()], 500);
        }
    }

    public function getMessages($conversationId) {
        $conv = Conversation::with(['messages.sender', 'service'])->find($conversationId);
        // Dans getMessages() ou index()
        $messages = Message::with(['sender:id,name,profile_photo_path', 'replyTo'])
                   ->where('conversation_id', $conversationId)
                   ->orderBy('created_at')
                   ->get();

        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Marquer comme lus
        $updated = Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated > 0) {
            Log::info("{$updated} messages marqués comme lus dans conv {$conversationId}");
        }

        return response()->json($conv);
    }

    // SEULE MÉTHODE MODIFIÉE — retourne le dernier message (desc limit 1)
    public function myConversations() {
        $userId = Auth::id();

        $conversations = Conversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with([
                // ← orderBy desc + limit 1 = dernier message garanti
                'messages' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(1);
                },
                'messages.sender',
                'userOne',
                'userTwo',
            ])
            ->latest('updated_at')
            ->get()
            ->map(function ($conv) use ($userId) {
                // Toujours calculer depuis les IDs bruts
                $conv->other_user = (int)$conv->user_one_id === (int)$userId
                    ? $conv->userTwo
                    : $conv->userOne;

                $conv->unread_count = Message::where('conversation_id', $conv->id)
                    ->where('sender_id', '!=', $userId)
                    ->whereNull('read_at')
                    ->count();

                return $conv;
            });

        return response()->json($conversations);
    }

    public function markAsRead($conversationId){
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

        if ($updated > 0) {
            $otherUserId = $conv->user_one_id === $userId
                ? $conv->user_two_id
                : $conv->user_one_id;

            if ($otherUserId) {
                $this->triggerPusher('private-user.' . $otherUserId, 'messages-read', [
                    'conversation_id' => $conv->id,
                    'user_id'         => $userId
                ]);
            }
        }

        return response()->json(['message' => 'Messages marqués lus', 'count' => $updated]);
    }

    public function typingIndicator(Request $request, $conversationId) {
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

        $receiverId = $conv->user_one_id === $userId
            ? $conv->user_two_id
            : $conv->user_one_id;

        if ($receiverId) {
            // Envoyer au canal de la conversation ET au canal user
            $this->triggerPusher('private-conversation.' . $conv->id, 'typing-indicator', [
                'conversation_id' => $conv->id,
                'user_id'         => $userId,
                'is_typing'       => $request->is_typing
            ]);
            $this->triggerPusher('private-user.' . $receiverId, 'typing-indicator', [
                'conversation_id' => $conv->id,
                'user_id'         => $userId,
                'is_typing'       => $request->is_typing
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function updateOnlineStatus(){
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        try {
            $user->last_seen_at = now();
            $user->save();

            $conversations = Conversation::where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id)
                ->get();

            foreach ($conversations as $conv) {
                $otherUserId = $conv->user_one_id === $user->id
                    ? $conv->user_two_id
                    : $conv->user_one_id;

                if ($otherUserId) {
                    $this->triggerPusher('private-user.' . $otherUserId, 'user-status', [
                        'user_id'   => $user->id,
                        'is_online' => true,
                        'last_seen' => $user->last_seen_at
                    ]);
                }
            }

            return response()->json([
                'message'      => 'Statut mis à jour',
                'last_seen_at' => $user->last_seen_at
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur updateOnlineStatus: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur de mise à jour'], 500);
        }
    }

    public function checkOnlineStatus($userId) {
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
                'user_id'      => $user->id,
                'is_online'    => $isOnline,
                'last_seen_at' => $user->last_seen_at
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'user_id'      => $user->id,
                'is_online'    => false,
                'last_seen_at' => $user->last_seen_at
            ]);
        }
    }

    private function isMember(Conversation $conv, ?int $userId): bool {
        return $conv->user_one_id === $userId || $conv->user_two_id === $userId;
    }

    private function getFileUrl($filePath) {
        if (!$filePath) return null;
        if (filter_var($filePath, FILTER_VALIDATE_URL)) return $filePath;
        return Storage::disk('public')->url($filePath);
    }

    private function uploadFile($file, $convId, $type) {
        $folderPath = "messages/{$convId}/{$type}";
        return $this->isCloudStorageEnabled()
            ? $this->uploadToCloudinary($file, $folderPath)
            : $this->uploadToLocalStorage($file, $folderPath);
    }

    private function isCloudStorageEnabled() {
        return env('CLOUDINARY_CLOUD_NAME') &&
               env('CLOUDINARY_API_KEY') &&
               env('CLOUDINARY_API_SECRET');
    }

    private function uploadToCloudinary($file, $folderPath) {
        try {
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key'    => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ],
                'url' => ['secure' => true]
            ]);
            $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder'        => $folderPath,
                'resource_type' => 'auto',
            ]);
            return $result['secure_url'];
        } catch (\Exception $e) {
            Log::error('Cloudinary upload error: ' . $e->getMessage());
            throw new Exception('Erreur upload Cloudinary');
        }
    }

    private function uploadToLocalStorage($file, $folderPath) {
        try {
            Storage::disk('public')->makeDirectory($folderPath);
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')->putFileAs($folderPath, $file, $fileName);
            return $folderPath . '/' . $fileName;
        } catch (\Exception $e) {
            Log::error('Erreur upload local: ' . $e->getMessage());
            throw new Exception('Erreur upload local');
        }
    }

    private function deleteFile($filePath) {
        if (!$filePath) return;
        if ($this->isCloudStorageEnabled() && filter_var($filePath, FILTER_VALIDATE_URL)) {
            try {
                $cloudinary = new Cloudinary([
                    'cloud' => [
                        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                        'api_key'    => env('CLOUDINARY_API_KEY'),
                        'api_secret' => env('CLOUDINARY_API_SECRET'),
                    ]
                ]);
                $publicId = pathinfo(parse_url($filePath, PHP_URL_PATH), PATHINFO_FILENAME);
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

    private function getDefaultContent($type) {
        return match ($type) {
            'image'    => 'Image',
            'video'    => 'Vidéo',
            'vocal'    => 'Message vocal',
            'document' => 'Document',
            default    => 'Message',
        };
    }

    public function sendMessageMobile(Request $request, $conversationId) {
        Log::info('Tentative d\'envoi de message', [
            'conversation_id' => $conversationId,
            'user_id' => Auth::id(),
            'has_file' => $request->hasFile('file'),
            'content' => $request->content
        ]);

        $validator = Validator::make($request->all(), [
            'type'         => 'required|in:text,image,video,vocal,document',
            'content'      => 'nullable|string',
            'file'         => 'nullable|file|max:20480', // 20MB max
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'temporary_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            Log::warning('Validation échouée', ['errors' => $validator->errors()]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conv = Conversation::find($conversationId);
        if (!$conv) {
            Log::error('Conversation introuvable', ['conversation_id' => $conversationId]);
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) {
            Log::warning('Utilisateur non autorisé', ['user_id' => $userId, 'conv_id' => $conversationId]);
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $filePath = null;
        $fileUrl  = null;

        try {
            DB::beginTransaction();

            // Upload du fichier si présent
            if ($request->hasFile('file')) {
                $filePath = $this->uploadFile($request->file('file'), $conv->id, $request->type);
                $fileUrl  = $this->getFileUrl($filePath);
                Log::info('Fichier uploadé', ['file_path' => $filePath, 'file_url' => $fileUrl]);
            }

            // Contenu par défaut si non fourni
            $content = $request->input('content');
            if (!$content && $filePath) {
                $content = $this->getDefaultContent($request->type);
            }

            // Création du message
            $message = Message::create([
                'conversation_id' => $conv->id,
                'sender_id'       => $userId,
                'content'         => $content,
                'type'            => $request->type,
                'file_path'       => $filePath,
                'latitude'        => $request->latitude,
                'longitude'       => $request->longitude,
                'temporary_id'    => $request->temporary_id,
                'reply_to_id'     => $request->reply_to_id ?? null
            ]);

            // Mettre à jour le timestamp de la conversation
            $conv->touch();
            
            // Charger les relations
            $message->load(['sender:id,name,profile_photo_path', 'replyTo']);

            $messageData = $message->toArray();
            $messageData['file_url'] = $fileUrl ?: $message->file_url;
            $messageData['is_me'] = true; // Pour le frontend

            DB::commit();

            Log::info('Message créé avec succès', ['message_id' => $message->id]);

            // Déterminer le destinataire
            $receiverId = $conv->user_one_id === $userId
                ? $conv->user_two_id
                : $conv->user_one_id;

            // Notifications Pusher
            if ($receiverId) {
                // Notifier le destinataire
                $this->triggerPusher('private-user.' . $receiverId, 'new-message', [
                    'conversation_id' => $conv->id,
                    'message'         => $messageData,
                    'sender_id'       => $userId,
                    'sender_name'     => Auth::user()->name
                ]);

                // Notifier la conversation
                $this->triggerPusher('private-conversation.' . $conv->id, 'message-sent', [
                    'message'   => $messageData,
                    'sender_id' => $userId
                ]);

                // Notification databaseaaa
                try {
                    $recipient = User::find($receiverId);
                    if ($recipient && $recipient->id !== $userId) {
                        $recipient->notify(new \App\Notifications\NewMessageNotification($message));
                    }
                } catch (\Exception $e) {
                    Log::warning('Erreur envoi notification', ['error' => $e->getMessage()]);
                }
            }

            return response()->json($messageData, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            if ($filePath) {
                $this->deleteFile($filePath);
            }
            Log::error('Erreur envoi message: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Erreur interne: ' . $e->getMessage()
            ], 500);
        }
    }

    public function startServiceConversationMobile(Request $request, $serviceId = null) {
        // Si $serviceId est passé dans l'URL
        if ($serviceId) {
            $request->merge(['service_id' => $serviceId]);
        }
        
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'message'    => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId    = Auth::id();
        $serviceId = $request->service_id;
        $service   = Service::with('entreprise.prestataire')->find($serviceId);

        if (!$service || !$service->entreprise) {
            return response()->json(['message' => 'Service introuvable'], 404);
        }

        $receiverId = $service->entreprise->prestataire_id;

        if ($userId == $receiverId) {
            return response()->json(['message' => 'Vous ne pouvez pas démarrer une conversation avec vous-même'], 422);
        }

        // Chercher une conversation existante
        $conversation = Conversation::where(function($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $userId)->where('user_two_id', $receiverId);
            })
            ->orWhere(function($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $receiverId)->where('user_two_id', $userId);
            })
            ->first();

        if (!$conversation) {
            // Créer une nouvelle conversation avec les infos du service
            $conversation = Conversation::create([
                'user_one_id'     => $userId,
                'user_two_id'     => $receiverId,
                'service_id'      => $serviceId,
                'service_name'    => $service->name,
                'entreprise_name' => $service->entreprise->name
            ]);
            Log::info('Nouvelle conversation créée', [
                'conversation_id' => $conversation->id,
                'service_id' => $serviceId
            ]);
        } else {
            // Mettre à jour la conversation avec les infos du service
            $conversation->update([
                'service_id'      => $serviceId,
                'service_name'    => $service->name,
                'entreprise_name' => $service->entreprise->name
            ]);
            Log::info('Conversation existante mise à jour', [
                'conversation_id' => $conversation->id
            ]);
        }

        // Si un message initial est fourni, l'envoyer
        if ($request->filled('message')) {
            // Créer un nouveau request pour l'envoi du message
            $messageRequest = new Request([
                'type'    => 'text',
                'content' => $request->message
            ]);
            
            // Appeler la méthode sendMessage
            return $this->sendMessage($messageRequest, $conversation->id);
        }

        $conversation->load(['userOne', 'userTwo', 'service']);
        return response()->json($conversation);
    }
}