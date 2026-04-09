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
use Illuminate\Support\Str;
use Pusher\Pusher;
use Exception;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\Messaging;


class MessageController extends Controller
{
    private ?Pusher $pusher = null;
    private bool $pusherEnabled = false;

    public function __construct()
    {
        $this->initPusher();
    }

    private function initPusher(): void
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
        }
    }

    private function triggerPusher(string $channel, string $event, array $data): bool
    {
        if (!$this->pusherEnabled || !$this->pusher) return false;
        try {
            $this->pusher->trigger($channel, $event, $data);
            return true;
        } catch (\Exception $e) {
            Log::error('Erreur Pusher: ' . $e->getMessage());
            return false;
        }
    }

    private function sendFCMNotification(User $recipient, array $payload): void
    {
        if (empty($recipient->fcm_token)) {
            return;
        }

        try {
            $messaging = Firebase::messaging();

            $message = CloudMessage::fromArray([
                'token'        => $recipient->fcm_token,
                'notification' => [
                    'title' => $payload['title'] ?? 'Notification',
                    'body'  => $payload['body'] ?? '',
                ],
                'data' => $payload['data'] ?? [],
            ]);

            $messaging->send($message);

            Log::info('[FCM] Notification envoyée à user#' . $recipient->id);
        } catch (\Exception $e) {
            Log::error('[FCM] Exception: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  FCM TOKEN
    // ──────────────────────────────────────────────────────────────────────
    public function saveFcmToken(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
            'platform'  => 'nullable|string|in:android,ios',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Token FCM enregistré',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  TYPING INDICATOR
    // ──────────────────────────────────────────────────────────────────────
    public function typingIndicator(Request $request, int $conversationId): \Illuminate\Http\JsonResponse
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

        $payload = [
            'conversation_id' => $conv->id,
            'user_id'         => $userId,
            'is_typing'       => $request->is_typing,
        ];

        if ($receiverId) {
            $this->triggerPusher('private-user.' . $receiverId, 'typing-indicator', $payload);
            $this->triggerPusher('private-conversation.' . $conv->id, 'typing-indicator', $payload);
        }

        return response()->json(['success' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  RECORDING INDICATOR
    // ──────────────────────────────────────────────────────────────────────
    public function recordingIndicator(Request $request, int $conversationId): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_recording' => 'required|boolean',
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

        $payload = [
            'conversation_id' => $conv->id,
            'user_id'         => $userId,
            'is_recording'    => $request->is_recording,
        ];

        if ($receiverId) {
            $this->triggerPusher('private-user.' . $receiverId, 'recording-indicator', $payload);
            $this->triggerPusher('private-conversation.' . $conv->id, 'recording-indicator', $payload);
        }

        return response()->json(['success' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  ENVOYER UN MESSAGE (MOBILE) — sans transaction explicite
    // ──────────────────────────────────────────────────────────────────────
    public function sendMessageMobile(Request $request, int $conversationId): \Illuminate\Http\JsonResponse
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
        $user   = User::find($userId);
        $user->update(['last_seen_at' => now()]);

        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $filePath = null;
        $fileUrl  = null;

        try {
            // Upload d'abord (hors transaction)
            if ($request->hasFile('file')) {
                $filePath = $this->uploadFile(
                    $request->file('file'),
                    $conv->id,
                    $request->type
                );
                $fileUrl = $this->getFileUrl($filePath);
            }

            $content = $request->input('content');
            if (!$content && $filePath) {
                $content = $this->getDefaultContent($request->type);
            }

            // Insertion directe sans BEGIN/COMMIT — PostgreSQL gère l'autocommit
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

            // touch() simple UPDATE sans transaction imbriquée
            $conv->touch();
            $message->load(['sender:id,name,profile_photo_path', 'replyTo']);

            $messageData             = $message->toArray();
            $messageData['file_url'] = $fileUrl ?: $message->file_url;
            $messageData['is_me']    = true;

            // ─── Destinataire ─────────────────────────────────────────
            $receiverId = $conv->user_one_id === $userId
                ? $conv->user_two_id
                : $conv->user_one_id;

            if ($receiverId) {
                $recipient = User::find($receiverId);

                $pusherPayload = [
                    'conversation_id' => $conv->id,
                    'message'         => $messageData,
                    'sender_id'       => $userId,
                    'sender_name'     => $user->name,
                    'sender_photo'    => $user->profile_photo_url ?? '',
                ];

                $this->triggerPusher('private-user.' . $receiverId, 'new-message', $pusherPayload);
                $this->triggerPusher('private-conversation.' . $conv->id, 'message-sent', [
                    'message'   => $messageData,
                    'sender_id' => $userId,
                ]);

                if ($recipient && !empty($recipient->fcm_token)) {
                    $this->sendFCMNotification($recipient, [
                        'title' => $user->name,
                        'body'  => $this->getNotificationBody($message),
                        'data'  => [
                            'conversation_id' => (string) $conv->id,
                            'sender_id'       => (string) $userId,
                            'sender_name'     => $user->name,
                            'sender_photo'    => $user->profile_photo_url ?? '',
                            'type'            => 'message',
                            'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                    ]);
                }
            }

            return response()->json($messageData, 201);

        } catch (\Exception $e) {
            if ($filePath && !isset($message)) {
                $this->deleteFile($filePath);
            }
            Log::error('Erreur envoi message mobile: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur interne: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getNotificationBody(Message $message): string
    {
        switch ($message->type) {
            case 'image':    return 'Photo';
            case 'video':    return 'Vidéo';
            case 'vocal':    return 'Message vocal';
            case 'document': return 'Document';
            default:
                $content = $message->content ?? '';
                return mb_strlen($content) > 100
                    ? mb_substr($content, 0, 100) . '…'
                    : $content;
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  MARQUER LU
    // ──────────────────────────────────────────────────────────────────────
    public function markAsRead(int $conversationId): \Illuminate\Http\JsonResponse
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
                $this->triggerPusher(
                    'private-user.' . $otherUserId,
                    'messages-read',
                    ['conversation_id' => $conv->id, 'user_id' => $userId]
                );
            }
        }

        return response()->json([
            'message' => 'Messages marqués lus',
            'count'   => $updated,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  DÉMARRER UNE CONVERSATION
    // ──────────────────────────────────────────────────────────────────────
    public function startConversation(Request $request): \Illuminate\Http\JsonResponse
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
            return response()->json([
                'message' => 'Vous ne pouvez pas démarrer une conversation avec vous-même',
            ], 422);
        }

        $conversation = Conversation::where(function ($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $userId)->where('user_two_id', $receiverId);
            })
            ->orWhere(function ($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $receiverId)->where('user_two_id', $userId);
            })
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $userId,
                'user_two_id' => $receiverId,
            ]);
        }

        $conversation->load(['userOne', 'userTwo']);
        return response()->json($conversation);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  DÉMARRER UNE CONVERSATION VIA SERVICE (mobile)
    // ──────────────────────────────────────────────────────────────────────
    public function startServiceConversationMobile(
        Request $request,
        ?int $serviceId = null
    ): \Illuminate\Http\JsonResponse {
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
            return response()->json([
                'message' => 'Vous ne pouvez pas vous contacter vous-même',
            ], 422);
        }

        $conversation = Conversation::where(function ($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $userId)->where('user_two_id', $receiverId);
            })
            ->orWhere(function ($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $receiverId)->where('user_two_id', $userId);
            })
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id'     => $userId,
                'user_two_id'     => $receiverId,
                'service_id'      => $serviceId,
                'service_name'    => $service->name,
                'entreprise_name' => $service->entreprise->name,
            ]);
        } else {
            $conversation->update([
                'service_id'      => $serviceId,
                'service_name'    => $service->name,
                'entreprise_name' => $service->entreprise->name,
            ]);
        }

        if ($request->filled('message')) {
            return $this->sendMessageMobile(
                new Request([
                    'type'    => 'text',
                    'content' => $request->message,
                ]),
                $conversation->id
            );
        }

        $conversation->load(['userOne', 'userTwo', 'service']);
        return response()->json($conversation);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  DÉMARRER UNE CONVERSATION VIA SERVICE (web)
    // ──────────────────────────────────────────────────────────────────────
    public function startServiceConversation(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'message'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId    = Auth::id();
        $user      = User::find($userId);
        $user->update(['last_seen_at' => now()]);

        $serviceId = $request->service_id;
        $service   = Service::with('entreprise.prestataire')->find($serviceId);

        if (!$service || !$service->entreprise) {
            return response()->json(['message' => 'Service introuvable'], 404);
        }

        $receiverId = $service->entreprise->prestataire_id;

        if ($userId == $receiverId) {
            return response()->json(['message' => 'Vous ne pouvez pas démarrer une conversation avec vous-même'], 422);
        }

        $conversation = Conversation::where(function ($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $userId)->where('user_two_id', $receiverId);
            })
            ->orWhere(function ($q) use ($userId, $receiverId) {
                $q->where('user_one_id', $receiverId)->where('user_two_id', $userId);
            })
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id'     => $userId,
                'user_two_id'     => $receiverId,
                'service_id'      => $serviceId,
                'service_name'    => $service->name,
                'entreprise_name' => $service->entreprise->name,
            ]);
        }

        if ($request->filled('message')) {
            return $this->sendMessageWithData($conversation->id, new Request([
                'type'    => 'text',
                'content' => $request->message,
            ]));
        }

        $conversation->load(['userOne', 'userTwo', 'service']);
        return response()->json($conversation);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  MES CONVERSATIONS
    // ──────────────────────────────────────────────────────────────────────
    public function myConversations(): \Illuminate\Http\JsonResponse
    {
        $userId = Auth::id();
        $user   = User::find($userId);
        $user->update(['last_seen_at' => now()]);

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
                $conv->other_user = (int) $conv->user_one_id === (int) $userId
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

    // ──────────────────────────────────────────────────────────────────────
    //  MESSAGES D'UNE CONVERSATION
    // ──────────────────────────────────────────────────────────────────────
    public function getMessages(int $conversationId): \Illuminate\Http\JsonResponse
    {
        $conv = Conversation::with([
            'messages.sender',
            'messages.replyTo.sender',
            'service',
        ])->find($conversationId);

        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $userId = Auth::id();
        $user   = User::find($userId);
        $user->update(['last_seen_at' => now()]);

        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $otherUser = $conv->user_one_id === $userId
            ? $conv->userTwo
            : $conv->userOne;

        Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $response               = $conv->toArray();
        $response['other_user'] = $otherUser ? $otherUser->toArray() : null;

        return response()->json($response);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  STATUT EN LIGNE
    // ──────────────────────────────────────────────────────────────────────
    public function updateOnlineStatus(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $user->update(['last_seen_at' => now()]);

        $conversations = Conversation::where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->get();

        foreach ($conversations as $conv) {
            $otherUserId = $conv->user_one_id === $user->id
                ? $conv->user_two_id
                : $conv->user_one_id;

            if ($otherUserId) {
                $this->triggerPusher(
                    'private-user.' . $otherUserId,
                    'user-status',
                    [
                        'user_id'      => $user->id,
                        'is_online'    => true,
                        'last_seen'    => $user->last_seen_at,
                        'last_seen_at' => $user->last_seen_at,
                    ]
                );
            }
        }

        return response()->json([
            'message'      => 'Statut mis à jour',
            'last_seen_at' => $user->last_seen_at,
        ]);
    }

    public function checkOnlineStatus(int $userId): \Illuminate\Http\JsonResponse
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

    // ──────────────────────────────────────────────────────────────────────
    //  ENVOYER UN MESSAGE (WEB)
    //  CORRECTION PRINCIPALE : le touch() est sorti de la transaction
    //  pour éviter l'erreur PostgreSQL 25P02
    // ──────────────────────────────────────────────────────────────────────
    public function sendMessage(Request $request, $conversationId)
    {
        return $this->sendMessageWithData($conversationId, $request);
    }

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
        $user   = User::find($userId);
        $user->update(['last_seen_at' => now()]);

        if (!$this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $filePath = null;
        $fileUrl  = null;

        try {
            // ── Upload AVANT la transaction pour éviter les longs locks ──
            if ($request->hasFile('file')) {
                $filePath = $this->uploadFile($request->file('file'), $conv->id, $request->type);
                $fileUrl  = $this->getFileUrl($filePath);
            }

            $content = $request->input('content');
            if (!$content && $filePath) {
                $content = $this->getDefaultContent($request->type);
            }

            // ── Transaction limitée à l'INSERT du message uniquement ──
            $message = DB::transaction(function () use ($conv, $userId, $content, $request, $filePath) {
                return Message::create([
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
            });

            // ── touch() HORS transaction — évite l'erreur 25P02 sur PostgreSQL ──
            $conv->touch();

            $message->load(['sender:id,name,profile_photo_path', 'replyTo']);

            $messageData             = $message->toArray();
            $messageData['file_url'] = $fileUrl ?: $message->file_url;

            // ─── Destinataire ─────────────────────────────────────────
            $receiverId = $conv->user_one_id === $userId
                ? $conv->user_two_id
                : $conv->user_one_id;

            if ($receiverId) {
                $this->triggerPusher('private-user.' . $receiverId, 'new-message', [
                    'conversation_id' => $conv->id,
                    'message'         => $messageData,
                    'sender_id'       => $userId,
                    'sender_name'     => $user->name,
                ]);

                $this->triggerPusher('private-conversation.' . $conv->id, 'message-sent', [
                    'message'   => $messageData,
                    'sender_id' => $userId,
                ]);

                $recipient = User::find($receiverId);
                if ($recipient && !empty($recipient->fcm_token)) {
                    $this->sendFCMNotification($recipient, [
                        'title' => $user->name,
                        'body'  => $this->getNotificationBody($message),
                        'data'  => [
                            'conversation_id' => (string) $conv->id,
                            'sender_id'       => (string) $userId,
                            'sender_name'     => $user->name,
                            'type'            => 'message',
                            'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                    ]);
                }
            }

            return response()->json($messageData, 201);

        } catch (\Exception $e) {
            if ($filePath && !isset($message)) {
                $this->deleteFile($filePath);
            }
            Log::error('Erreur envoi message: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur interne: ' . $e->getMessage()], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  MODIFIER UN MESSAGE
    // ──────────────────────────────────────────────────────────────────────
    public function update(Request $request, int $id)
    {
        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $message = Message::find($id);

        if (!$message) {
            return response()->json(['message' => 'Message introuvable'], 404);
        }

        if ((int) $message->sender_id !== (int) Auth::id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($message->type !== 'text') {
            return response()->json(['message' => 'Seuls les messages texte peuvent être modifiés'], 422);
        }

        if (now()->diffInMinutes($message->created_at) > 15) {
            return response()->json([
                'message' => 'Délai de modification dépassé (15 minutes max)',
            ], 422);
        }

        $message->content = $request->content;
        $message->edited  = true;
        $message->save();

        $message->load(['sender:id,name,profile_photo_path', 'replyTo']);

        return response()->json([
            'message' => 'Message modifié',
            'data'    => $message,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  SUPPRIMER UN MESSAGE
    // ──────────────────────────────────────────────────────────────────────
    public function destroy(int $id)
    {
        $message = Message::find($id);

        if (!$message) {
            return response()->json(['message' => 'Message introuvable'], 404);
        }

        if ((int) $message->sender_id !== (int) Auth::id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $message->delete();

        return response()->json(['message' => 'Message supprimé']);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  HELPERS PRIVÉS
    // ──────────────────────────────────────────────────────────────────────
    private function isMember(Conversation $conv, ?int $userId): bool
    {
        return $conv->user_one_id === $userId || $conv->user_two_id === $userId;
    }

    private function getFileUrl(?string $filePath): ?string
    {
        if (!$filePath) return null;
        if (filter_var($filePath, FILTER_VALIDATE_URL)) return $filePath;
        return Storage::disk('public')->url($filePath);
    }

    private function uploadFile($file, int $convId, string $type): string
    {
        $folderPath = "messages/{$convId}/{$type}";
        return $this->isCloudStorageEnabled()
            ? $this->uploadToCloudinary($file, $folderPath)
            : $this->uploadToLocalStorage($file, $folderPath);
    }

    private function isCloudStorageEnabled(): bool
    {
        return !empty(env('CLOUDINARY_CLOUD_NAME'))
            && !empty(env('CLOUDINARY_API_KEY'))
            && !empty(env('CLOUDINARY_API_SECRET'));
    }

    private function uploadToCloudinary($file, string $folderPath): string
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
            $result = $cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                ['folder' => $folderPath, 'resource_type' => 'auto']
            );
            return $result['secure_url'];
        } catch (\Exception $e) {
            Log::error('Cloudinary upload error: ' . $e->getMessage());
            throw new \Exception('Erreur upload Cloudinary');
        }
    }

    private function uploadToLocalStorage($file, string $folderPath): string
    {
        try {
            Storage::disk('public')->makeDirectory($folderPath);
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')->putFileAs($folderPath, $file, $fileName);
            return $folderPath . '/' . $fileName;
        } catch (\Exception $e) {
            Log::error('Erreur upload local: ' . $e->getMessage());
            throw new \Exception('Erreur upload local');
        }
    }

    private function deleteFile(?string $filePath): void
    {
        if (!$filePath) return;
        try {
            if (filter_var($filePath, FILTER_VALIDATE_URL)) {
                if ($this->isCloudStorageEnabled()) {
                    $cloudinary = new Cloudinary([
                        'cloud' => [
                            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                            'api_key'    => env('CLOUDINARY_API_KEY'),
                            'api_secret' => env('CLOUDINARY_API_SECRET'),
                        ],
                    ]);
                    $publicId = pathinfo(parse_url($filePath, PHP_URL_PATH), PATHINFO_FILENAME);
                    $cloudinary->uploadApi()->destroy($publicId);
                }
            } else {
                Storage::disk('public')->delete($filePath);
            }
        } catch (\Exception $e) {
            Log::error('Erreur suppression fichier: ' . $e->getMessage());
        }
    }

    private function getDefaultContent(string $type): string
    {
        return match ($type) {
            'image'    => 'Image',
            'video'    => 'Vidéo',
            'vocal'    => 'Message vocal',
            'document' => 'Document',
            default    => 'Message',
        };
    }
}