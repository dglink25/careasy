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
    private $pusher       = null;
    private $pusherEnabled = false;

    public function __construct()
    {
        $this->initPusher();
    }

    // ── Pusher ───────────────────────────────────────────────────────────────

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
                        'useTLS'  => true,
                        'timeout' => 30,
                    ]
                );
                $this->pusherEnabled = true;
            }
        } catch (\Exception $e) {
            Log::error('Pusher non disponible: ' . $e->getMessage());
            $this->pusherEnabled = false;
        }
    }

    private function triggerPusher($channel, $event, $data)
    {
        if (!$this->pusherEnabled || !$this->pusher) return false;

        try {
            $result = $this->pusher->trigger($channel, $event, $data);
            Log::info('Pusher envoyé', ['channel' => $channel, 'event' => $event]);
            return $result;
        } catch (\Exception $e) {
            Log::error('Erreur Pusher: ' . $e->getMessage());
            return false;
        }
    }

    // ── Démarrer une conversation ────────────────────────────────────────────

    public function startConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId     = Auth::id();
        $receiverId = $request->receiver_id;

        if ($userId == $receiverId) {
            return response()->json(['message' => 'Impossible de vous écrire à vous-même'], 422);
        }

        $conversation = Conversation::where(function ($q) use ($userId, $receiverId) {
            $q->where('user_one_id', $userId)->where('user_two_id', $receiverId);
        })->orWhere(function ($q) use ($userId, $receiverId) {
            $q->where('user_one_id', $receiverId)->where('user_two_id', $userId);
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $userId,
                'user_two_id' => $receiverId,
            ]);
        }

        $conversation->load(['userOne', 'userTwo']);
        return response()->json($conversation);
    }

    // ── Démarrer une conversation via service (web) ──────────────────────────

    public function startServiceConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'message'    => 'nullable|string',
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
            return response()->json(['message' => 'Impossible de vous écrire à vous-même'], 422);
        }

        $conversation = $this->findOrCreateConversation($userId, $receiverId, [
            'service_id'      => $serviceId,
            'service_name'    => $service->name,
            'entreprise_name' => $service->entreprise->name,
        ]);

        if ($request->filled('message')) {
            $messageRequest = new Request([
                'type'    => 'text',
                'content' => $request->message,
            ]);
            return $this->sendMessageWithData($conversation->id, $messageRequest);
        }

        $conversation->load(['userOne', 'userTwo', 'service']);
        return response()->json($conversation);
    }

    // ── Démarrer une conversation via service (mobile) ───────────────────────

    public function startServiceConversationMobile(Request $request, $serviceId = null)
    {
        if ($serviceId) {
            $request->merge(['service_id' => $serviceId]);
        }

        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'message'    => 'nullable|string',
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
            return response()->json(['message' => 'Impossible de vous écrire à vous-même'], 422);
        }

        $conversation = $this->findOrCreateConversation($userId, $receiverId, [
            'service_id'      => $serviceId,
            'service_name'    => $service->name,
            'entreprise_name' => $service->entreprise->name,
        ]);

        if ($request->filled('message')) {
            $messageRequest = new Request([
                'type'    => 'text',
                'content' => $request->message,
            ]);
            return $this->sendMessageWithData($conversation->id, $messageRequest);
        }

        $conversation->load(['userOne', 'userTwo', 'service']);

        // Formater pour le mobile
        $otherUser = $conversation->user_one_id === $userId
            ? $conversation->userTwo
            : $conversation->userOne;

        return response()->json([
            'id'              => $conversation->id,
            'other_user'      => $this->formatUser($otherUser),
            'service_name'    => $conversation->service_name,
            'entreprise_name' => $conversation->entreprise_name,
            'service_id'      => $conversation->service_id,
            'unread_count'    => 0,
            'updated_at'      => $conversation->updated_at,
        ]);
    }

    // ── Envoyer un message (route web) ───────────────────────────────────────

    public function sendMessage(Request $request, $conversationId)
    {
        return $this->sendMessageWithData($conversationId, $request);
    }

    // ── Envoyer un message (route mobile) ────────────────────────────────────

    public function sendMessageMobile(Request $request, $conversationId)
    {
        Log::info('sendMessageMobile', [
            'conversation_id' => $conversationId,
            'user_id'         => Auth::id(),
            'type'            => $request->type,
            'has_file'        => $request->hasFile('file'),
        ]);

        // Accepter "location" comme alias de "text" pour le type
        if ($request->input('type') === 'location') {
            $request->merge(['type' => 'text']);
        }

        $validator = Validator::make($request->all(), [
            'type'         => 'required|in:text,image,video,vocal,document',
            'content'      => 'nullable|string|max:5000',
            'file'         => 'nullable|file|max:20480',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'temporary_id' => 'nullable|string|max:100',
            'reply_to_id'  => 'nullable|exists:messages,id',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation échouée sendMessageMobile', $validator->errors()->toArray());
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

            // Stocker le type original (peut être "location" dans le contenu,
            // mais on enregistre "text" dans la BD avec les coordonnées)
            $message = Message::create([
                'conversation_id' => $conv->id,
                'sender_id'       => $userId,
                'content'         => $content,
                'type'            => $request->type,
                'file_path'       => $filePath,
                'latitude'        => $request->latitude,
                'longitude'       => $request->longitude,
                'temporary_id'    => $request->temporary_id,
                'reply_to_id'     => $request->reply_to_id ?? null,
            ]);

            $conv->touch();

            $message->load(['sender:id,name,profile_photo_path', 'replyTo']);

            $messageData              = $message->toArray();
            $messageData['file_url']  = $fileUrl ?: $message->file_url;
            $messageData['is_me']     = true; // Pour l'expéditeur mobile

            // Inclure les coordonnées dans la réponse si présentes
            if ($request->latitude)  $messageData['latitude']  = $request->latitude;
            if ($request->longitude) $messageData['longitude'] = $request->longitude;

            // Si le contenu est une localisation, forcer le type côté client
            if ($request->latitude && $request->longitude && $request->type === 'text') {
                $messageData['type'] = 'location';
            }

            DB::commit();

            // ── Notifications temps réel ─────────────────────────────────────
            $receiverId = $conv->user_one_id === $userId
                ? $conv->user_two_id
                : $conv->user_one_id;

            if ($receiverId) {
                // Le destinataire ne doit pas avoir is_me: true
                $receiverData           = $messageData;
                $receiverData['is_me']  = false;

                $this->triggerPusher('private-user.' . $receiverId, 'new-message', [
                    'conversation_id' => $conv->id,
                    'message'         => $receiverData,
                    'sender_id'       => $userId,
                    'sender_name'     => Auth::user()->name,
                ]);

                $this->triggerPusher('private-conversation.' . $conv->id, 'message-sent', [
                    'message'   => $receiverData,
                    'sender_id' => $userId,
                ]);

                // Notification base de données + FCM
                try {
                    $recipient = User::find($receiverId);
                    if ($recipient) {
                        $recipient->notify(new \App\Notifications\NewMessageNotification($message));
                    }
                } catch (\Exception $e) {
                    Log::warning('Erreur notification: ' . $e->getMessage());
                }
            }

            return response()->json($messageData, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            if ($filePath) $this->deleteFile($filePath);
            Log::error('Erreur sendMessageMobile: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur interne: ' . $e->getMessage()], 500);
        }
    }

    // ── Récupérer les messages d'une conversation ────────────────────────────

    /**
     * ⭐ CORRIGÉ : retourne { messages: [...], conversation: {...} }
     * au lieu de $conv directement (qui ne contenait pas les bons messages)
     */
    public function getMessages($conversationId)
    {
        $conv = Conversation::with(['userOne', 'userTwo', 'service'])->find($conversationId);

        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Marquer les messages comme lus
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
                    'user_id'         => $userId,
                ]);
            }
        }

        // Récupérer tous les messages avec leurs relations
        $messages = Message::with(['sender:id,name,profile_photo_path', 'replyTo'])
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) use ($userId) {
                $data           = $msg->toArray();
                $data['is_me']  = (int) $msg->sender_id === (int) $userId;
                $data['file_url'] = $msg->file_url;

                // Reconstruire le type "location" si le message a des coordonnées
                if ($msg->type === 'text' && $msg->latitude && $msg->longitude) {
                    $data['type'] = 'location';
                }

                return $data;
            });

        $otherUser = $conv->user_one_id === $userId ? $conv->userTwo : $conv->userOne;

        return response()->json([
            'messages'     => $messages,
            'conversation' => [
                'id'              => $conv->id,
                'service_name'    => $conv->service_name,
                'entreprise_name' => $conv->entreprise_name,
            ],
            'other_user'   => $this->formatUser($otherUser),
        ]);
    }

    // ── Liste des conversations ───────────────────────────────────────────────

    public function myConversations()
    {
        $userId = Auth::id();

        $conversations = Conversation::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with([
                'messages' => function ($q) {
                    $q->orderBy('created_at', 'desc')->limit(1);
                },
                'messages.sender',
                'userOne',
                'userTwo',
            ])
            ->latest('updated_at')
            ->get()
            ->map(function ($conv) use ($userId) {
                $otherUser = (int) $conv->user_one_id === (int) $userId
                    ? $conv->userTwo
                    : $conv->userOne;

                $lastMsg = $conv->messages->first();
                $lastMessageData = null;
                if ($lastMsg) {
                    $lastMessageData = [
                        'id'              => $lastMsg->id,
                        'content'         => $lastMsg->content,
                        'type'            => $lastMsg->type,
                        'sender_id'       => $lastMsg->sender_id,
                        'is_me'           => (int) $lastMsg->sender_id === (int) $userId,
                        'created_at'      => $lastMsg->created_at,
                        'read_at'         => $lastMsg->read_at,
                        'conversation_id' => $conv->id,
                        'file_url'        => $lastMsg->file_url,
                    ];
                }

                $unreadCount = Message::where('conversation_id', $conv->id)
                    ->where('sender_id', '!=', $userId)
                    ->whereNull('read_at')
                    ->count();

                return [
                    'id'              => $conv->id,
                    'other_user'      => $this->formatUser($otherUser),
                    'last_message'    => $lastMessageData,
                    'unread_count'    => $unreadCount,
                    'updated_at'      => $conv->updated_at,
                    'service_name'    => $conv->service_name,
                    'entreprise_name' => $conv->entreprise_name,
                    'service_id'      => $conv->service_id,
                ];
            });

        return response()->json($conversations);
    }

    // ── Marquer comme lu ─────────────────────────────────────────────────────

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

        if ($updated > 0) {
            $otherUserId = $conv->user_one_id === $userId
                ? $conv->user_two_id
                : $conv->user_one_id;

            if ($otherUserId) {
                $this->triggerPusher('private-user.' . $otherUserId, 'messages-read', [
                    'conversation_id' => $conv->id,
                    'user_id'         => $userId,
                ]);
            }
        }

        return response()->json(['message' => 'Messages marqués lus', 'count' => $updated]);
    }

    // ── Typing indicator ─────────────────────────────────────────────────────

    public function typingIndicator(Request $request, $conversationId)
    {
        $validator = Validator::make($request->all(), [
            'is_typing' => 'required|boolean',
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
            $payload = [
                'conversation_id' => $conv->id,
                'user_id'         => $userId,
                'is_typing'       => $request->is_typing,
            ];
            $this->triggerPusher('private-conversation.' . $conv->id, 'typing-indicator', $payload);
            $this->triggerPusher('private-user.' . $receiverId, 'typing-indicator', $payload);
        }

        return response()->json(['success' => true]);
    }

    // ── Recording indicator ──────────────────────────────────────────────────

    public function recordingIndicator(Request $request, $conversationId)
    {
        $conv   = Conversation::find($conversationId);
        $userId = Auth::id();
        if (!$conv || !$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $receiverId = $conv->user_one_id === $userId
            ? $conv->user_two_id
            : $conv->user_one_id;

        if ($receiverId) {
            $this->triggerPusher('private-user.' . $receiverId, 'recording-indicator', [
                'conversation_id' => $conv->id,
                'user_id'         => $userId,
                'is_recording'    => $request->boolean('is_recording'),
            ]);
        }

        return response()->json(['success' => true]);
    }

    // ── Statut en ligne ──────────────────────────────────────────────────────

    public function updateOnlineStatus()
    {
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
                        'last_seen' => $user->last_seen_at,
                    ]);
                }
            }

            return response()->json([
                'message'      => 'Statut mis à jour',
                'last_seen_at' => $user->last_seen_at,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur updateOnlineStatus: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    public function checkOnlineStatus($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

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
            'last_seen_at' => $user->last_seen_at,
        ]);
    }

    // ── Token FCM ────────────────────────────────────────────────────────────

    /**
     * Sauvegarder le token FCM de l'utilisateur pour les notifications push.
     * Route: POST /api/user/fcm-token
     */
    public function saveFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|max:500',
            'platform'  => 'nullable|in:android,ios',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = Auth::user();
            $user->fcm_token = $request->fcm_token;
            $user->save();

            Log::info('Token FCM sauvegardé', [
                'user_id'  => $user->id,
                'platform' => $request->platform,
            ]);

            return response()->json(['message' => 'Token FCM sauvegardé']);
        } catch (\Exception $e) {
            Log::error('Erreur sauvegarde token FCM: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function findOrCreateConversation($userId, $receiverId, array $extra = [])
    {
        $conversation = Conversation::where(function ($q) use ($userId, $receiverId) {
            $q->where('user_one_id', $userId)->where('user_two_id', $receiverId);
        })->orWhere(function ($q) use ($userId, $receiverId) {
            $q->where('user_one_id', $receiverId)->where('user_two_id', $userId);
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create(array_merge([
                'user_one_id' => $userId,
                'user_two_id' => $receiverId,
            ], $extra));
        } elseif (!empty($extra)) {
            $conversation->update($extra);
        }

        return $conversation;
    }

    private function formatUser(?User $user): array
    {
        if (!$user) return [];

        $isOnline = false;
        if ($user->last_seen_at) {
            $lastSeen = is_string($user->last_seen_at)
                ? \Carbon\Carbon::parse($user->last_seen_at)
                : $user->last_seen_at;
            $isOnline = $lastSeen->diffInMinutes(now()) < 5;
        }

        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'profile_photo_url' => $user->profile_photo_path
                ? Storage::disk('public')->url($user->profile_photo_path)
                : null,
            'is_online'         => $isOnline,
            'last_seen'         => $user->last_seen_at,
            'role'              => $user->role ?? null,
            'phone'             => $user->phone ?? null,
        ];
    }

    private function isMember(Conversation $conv, ?int $userId): bool
    {
        return (int) $conv->user_one_id === (int) $userId
            || (int) $conv->user_two_id === (int) $userId;
    }

    private function getFileUrl($filePath)
    {
        if (!$filePath) return null;
        if (filter_var($filePath, FILTER_VALIDATE_URL)) return $filePath;
        return Storage::disk('public')->url($filePath);
    }

    private function uploadFile($file, $convId, $type)
    {
        $folderPath = "messages/{$convId}/{$type}";
        return $this->isCloudStorageEnabled()
            ? $this->uploadToCloudinary($file, $folderPath)
            : $this->uploadToLocalStorage($file, $folderPath);
    }

    private function isCloudStorageEnabled(): bool
    {
        return env('CLOUDINARY_CLOUD_NAME')
            && env('CLOUDINARY_API_KEY')
            && env('CLOUDINARY_API_SECRET');
    }

    private function uploadToCloudinary($file, $folderPath)
    {
        try {
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key'    => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ],
                'url' => ['secure' => true],
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

    private function uploadToLocalStorage($file, $folderPath)
    {
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

    private function deleteFile($filePath)
    {
        if (!$filePath) return;

        if ($this->isCloudStorageEnabled() && filter_var($filePath, FILTER_VALIDATE_URL)) {
            try {
                $cloudinary = new Cloudinary([
                    'cloud' => [
                        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                        'api_key'    => env('CLOUDINARY_API_KEY'),
                        'api_secret' => env('CLOUDINARY_API_SECRET'),
                    ],
                ]);
                $publicId = pathinfo(parse_url($filePath, PHP_URL_PATH), PATHINFO_FILENAME);
                $cloudinary->uploadApi()->destroy($publicId);
            } catch (\Exception $e) {
                Log::error('Erreur suppression Cloudinary: ' . $e->getMessage());
            }
        } else {
            Storage::disk('public')->delete($filePath);
        }
    }

    private function getDefaultContent($type): string
    {
        return match ($type) {
            'image'    => '',
            'video'    => '',
            'vocal'    => '',
            'document' => '',
            default    => 'Message',
        };
    }

    // ── Méthode privée partagée (web) ────────────────────────────────────────

    private function sendMessageWithData($conversationId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'         => 'required|in:text,image,video,vocal,document',
            'content'      => 'nullable|string',
            'file'         => 'nullable|file|max:20480',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'temporary_id' => 'nullable|string',
            'reply_to_id'  => 'nullable|exists:messages,id',
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
                'reply_to_id'     => $request->reply_to_id ?? null,
            ]);

            $conv->touch();
            $message->load(['sender', 'replyTo']);

            $messageData              = $message->toArray();
            $messageData['file_url']  = $fileUrl ?: $message->file_url;
            $messageData['is_me']     = true;

            DB::commit();

            $receiverId = $conv->user_one_id === $userId
                ? $conv->user_two_id
                : $conv->user_one_id;

            if ($receiverId) {
                $receiverData          = $messageData;
                $receiverData['is_me'] = false;

                $this->triggerPusher('private-user.' . $receiverId, 'new-message', [
                    'conversation_id' => $conv->id,
                    'message'         => $receiverData,
                    'sender_id'       => $userId,
                    'sender_name'     => Auth::user()->name,
                ]);

                $recipient = User::find($receiverId);
                if ($recipient) {
                    $recipient->notify(new \App\Notifications\NewMessageNotification($message));
                }
            }

            return response()->json($messageData, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            if ($filePath) $this->deleteFile($filePath);
            Log::error('Erreur sendMessage: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne: ' . $e->getMessage()], 500);
        }
    }
}