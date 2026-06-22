<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Pusher\Pusher;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class MessageController extends Controller{
    private ?Pusher $pusher = null;
    private bool $pusherEnabled = false;

    public function __construct(){
        $this->initPusher();
    }

    private function initPusher(): void {
        try {
            $appId  = config('broadcasting.connections.pusher.app_id');
            $key    = config('broadcasting.connections.pusher.key');
            $secret = config('broadcasting.connections.pusher.secret');
            $cluster = config('broadcasting.connections.pusher.options.cluster', 'eu');

            if ($appId && $key && $secret) {
                $this->pusher = new Pusher(
                    $key,
                    $secret,
                    $appId,
                    [
                        'cluster' => $cluster,
                        'useTLS'  => true,
                        'timeout' => 30,
                    ]
                );
                $this->pusherEnabled = true;
            } 
            else {
                Log::warning('Pusher: credentials manquants dans config/broadcasting.php');
            }
        } catch (\Exception $e) {
            Log::error('Pusher non disponible: ' . $e->getMessage());
        }
    }

    private function triggerPusher(string $channel, string $event, array $data): bool {
        if (!$this->pusherEnabled || !$this->pusher) return false;
        try {
            $this->pusher->trigger($channel, $event, $data);
            return true;
        } catch (\Exception $e) {
            Log::error('Erreur Pusher: ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    //  FCM
    // =========================================================================

    private function sendFCMNotification(User $recipient, array $payload): void{
        if (empty($recipient->fcm_token)) return;

        try {
            $messaging = Firebase::messaging();

            $fcmMessage = CloudMessage::fromArray([
                'token' => $recipient->fcm_token,
                'notification' => [
                    'title' => $payload['title'] ?? 'Notification',
                    'body'  => $payload['body']  ?? '',
                ],
                'data' => array_map('strval', $payload['data'] ?? []),
            ]);

            $messaging->send($fcmMessage);
            Log::info('[FCM] Notification envoyée à user#' . $recipient->id);
        } catch (\Exception $e) {
            Log::error('[FCM] Exception: ' . $e->getMessage());
        }
    }

    private function getNotificationBody(Message $message): string
    {
        return match ($message->type) {
            'image'    => 'Photo',
            'video'    => 'Vidéo',
            'vocal'    => 'Message vocal',
            'document' => 'Document',
            default    => mb_strlen($message->content ?? '') > 100
                ? mb_substr($message->content, 0, 100) . '…'
                : ($message->content ?? ''),
        };
    }

    // =========================================================================
    //  FCM TOKEN
    // =========================================================================

    public function saveFcmToken(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
            'platform'  => 'nullable|string|in:android,ios',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find(Auth::id());
        if (!$user) return response()->json(['message' => 'Non authentifié'], 401);

        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json(['success' => true, 'message' => 'Token FCM enregistré']);
    }

    // =========================================================================
    //  INDICATEURS TEMPS RÉEL
    // =========================================================================

    public function typingIndicator(Request $request, int $conversationId): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_typing' => 'required|boolean',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $conv = Conversation::find($conversationId);
        if (!$conv) return response()->json(['message' => 'Conversation introuvable'], 404);

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) return response()->json(['message' => 'Non autorisé'], 403);

        $receiverId = $conv->user_one_id === $userId ? $conv->user_two_id : $conv->user_one_id;

        $payload = [
            'conversation_id' => $conv->id,
            'user_id'         => $userId,
            'is_typing'       => $request->is_typing,
        ];

        if ($receiverId) {
            $this->triggerPusher('private-user.' . $receiverId,   'typing-indicator', $payload);
            $this->triggerPusher('private-conversation.' . $conv->id, 'typing-indicator', $payload);
        }

        return response()->json(['success' => true]);
    }

    public function recordingIndicator(Request $request, int $conversationId): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_recording' => 'required|boolean',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $conv = Conversation::find($conversationId);
        if (!$conv) return response()->json(['message' => 'Conversation introuvable'], 404);

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) return response()->json(['message' => 'Non autorisé'], 403);

        $receiverId = $conv->user_one_id === $userId ? $conv->user_two_id : $conv->user_one_id;

        $payload = [
            'conversation_id' => $conv->id,
            'user_id'         => $userId,
            'is_recording'    => $request->is_recording,
        ];

        if ($receiverId) {
            $this->triggerPusher('private-user.' . $receiverId,       'recording-indicator', $payload);
            $this->triggerPusher('private-conversation.' . $conv->id, 'recording-indicator', $payload);
        }

        return response()->json(['success' => true]);
    }


    public function sendMessageMobile(Request $request, int $conversationId): \Illuminate\Http\JsonResponse {
        return $this->handleSend($request, $conversationId, isMobile: true);
    }

    public function sendMessage(Request $request, int $conversationId): \Illuminate\Http\JsonResponse {
        return $this->handleSend($request, $conversationId, isMobile: false);
    }


    private function handleSend(Request $request, int $conversationId, bool $isMobile = true): \Illuminate\Http\JsonResponse {
        $validator = Validator::make($request->all(), [
            'type'         => 'required|in:text,image,video,vocal,document',
            'content'      => 'nullable|string|max:10000',
            // Tous les médias : 100 Mo max (102400 Ko)
            'file'         => 'nullable|file|max:102400',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'temporary_id' => 'nullable|string',
            'reply_to_id'  => 'nullable|exists:messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors'  => $validator->errors(),
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $conv = Conversation::find($conversationId);
        if (!$conv) return response()->json(['message' => 'Conversation introuvable'], 404);

        $userId = Auth::id();
        $user   = User::find($userId);

        if (!$user) return response()->json(['message' => 'Non authentifié'], 401);
        if (!$this->isMember($conv, $userId)) return response()->json(['message' => 'Non autorisé'], 403);

        $user->update(['last_seen_at' => now()]);

        $filePath = null;
        $fileUrl  = null;

        try {
            // ── Upload fichier ──────────────────────────────────────────
            if ($request->hasFile('file') && $request->file('file')->isValid()) {
                $filePath = $this->uploadFile($request->file('file'), $conv->id, $request->type);
                $fileUrl  = $this->getFileUrl($filePath);
            }

            // Contenu par défaut si fichier sans texte
            $content = $request->input('content');
            if (empty($content) && $filePath) {
                $content = $this->getDefaultContent($request->type);
            }

            // ── Création du message ─────────────────────────────────────
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

            // Construire la réponse JSON
            $messageData             = $message->toArray();
            $messageData['file_url'] = $fileUrl ?? $message->file_url;
            // is_me est calculé côté client selon sender_id — ne pas l'inclure dans Pusher
            // (sinon le récepteur croit que c'est son propre message)
            $messageDataForHttp          = $messageData;
            $messageDataForHttp['is_me'] = true;  // uniquement pour la réponse HTTP à l'émetteur

            // ── Notification Pusher + FCM ───────────────────────────────
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

                $this->triggerPusher('private-user.' . $receiverId,       'new-message',   $pusherPayload);
                $this->triggerPusher('private-conversation.' . $conv->id, 'message-sent',
                    ['message' => $messageData, 'sender_id' => $userId]);

                // FCM — capturer $this dans la closure pour éviter l'erreur de scope
                if ($recipient && !empty($recipient->fcm_token)) {
                    $controller = $this;
                    $notifData  = [
                        'title' => $user->name,
                        'body'  => $this->getNotificationBody($message),
                        'data'  => [
                            'conversation_id' => (string) $message->conversation_id,
                            'sender_id'       => (string) $user->id,
                            'sender_name'     => $user->name,
                            'sender_photo'    => $user->profile_photo_url ?? '',
                            'type'            => 'message',
                            'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                    ];
                    dispatch(static function () use ($controller, $recipient, $notifData) {
                        $controller->sendFCMNotification($recipient, $notifData);
                    })->afterResponse();
                }
            }

            return response()->json($messageDataForHttp, 201);

        } catch (\Exception $e) {
            // Nettoyage si le fichier a été uploadé mais le message non sauvegardé
            if ($filePath && !isset($message)) {
                $this->deleteFile($filePath);
            }
            Log::error('Erreur envoi message: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur interne: ' . $e->getMessage()], 500);
        }
    }

    public function markAsRead(int $conversationId): \Illuminate\Http\JsonResponse  {
        $conv = Conversation::find($conversationId);
        if (!$conv) return response()->json(['message' => 'Conversation introuvable'], 404);

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) return response()->json(['message' => 'Non autorisé'], 403);

        $updated = Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated > 0) {
            $otherUserId = $conv->user_one_id === $userId ? $conv->user_two_id : $conv->user_one_id;
            if ($otherUserId) {
                $this->triggerPusher(
                    'private-user.' . $otherUserId,
                    'messages-read',
                    ['conversation_id' => $conv->id, 'user_id' => $userId]
                );
            }
        }

        return response()->json(['message' => 'Messages marqués lus', 'count' => $updated]);
    }

    // =========================================================================
    //  DÉMARRER / RÉCUPÉRER DES CONVERSATIONS
    // =========================================================================

    public function startConversation(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $userId     = Auth::id();
        $receiverId = (int) $request->receiver_id;

        if ($userId === $receiverId) {
            return response()->json(['message' => 'Vous ne pouvez pas démarrer une conversation avec vous-même'], 422);
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

        $conversation->load(['userOne:id,name,email,profile_photo_path,last_seen_at', 'userTwo:id,name,email,profile_photo_path,last_seen_at']);
        return response()->json($conversation);
    }

    /**
     * POST /api/conversation/service/{serviceId}/start  (mobile)
     * POST /api/conversation/service                    (legacy)
     */
    public function startServiceConversationMobile(Request $request, ?int $serviceId = null): \Illuminate\Http\JsonResponse
    {
        if ($serviceId) $request->merge(['service_id' => $serviceId]);
        return $this->handleStartServiceConversation($request);
    }

    public function startServiceConversation(Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->handleStartServiceConversation($request);
    }

    private function handleStartServiceConversation(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'message'    => 'nullable|string',
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $userId    = Auth::id();
        $serviceId = (int) $request->service_id;
        $service   = Service::with('entreprise.prestataire')->find($serviceId);

        if (!$service || !$service->entreprise) {
            return response()->json(['message' => 'Service introuvable'], 404);
        }

        $receiverId = $service->entreprise->prestataire_id;
        if ($userId === $receiverId) {
            return response()->json(['message' => 'Vous ne pouvez pas vous contacter vous-même'], 422);
        }

        // ──────────────────────────────────────────────────────────────────────
        // RÈGLE MÉTIER : une conversation = (client, prestataire, service)
        // Chaque service a son propre fil de discussion. Chercher d'abord
        // une conversation existante pour ce triplet exact.
        // ──────────────────────────────────────────────────────────────────────
        $conversation = Conversation::where('service_id', $serviceId)
            ->where(function ($q) use ($userId, $receiverId) {
                $q->where(function ($inner) use ($userId, $receiverId) {
                    $inner->where('user_one_id', $userId)
                          ->where('user_two_id', $receiverId);
                })->orWhere(function ($inner) use ($userId, $receiverId) {
                    $inner->where('user_one_id', $receiverId)
                          ->where('user_two_id', $userId);
                });
            })
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id'    => $userId,
                'user_two_id'    => $receiverId,
                'service_id'     => $serviceId,
                'service_name'   => $service->name,
                'entreprise_name'=> $service->entreprise->name,
            ]);
        }
        // Ne jamais écraser le service_id d'une conversation existante

        if ($request->filled('message')) {
            return $this->handleSend(
                new Request(['type' => 'text', 'content' => $request->message]),
                $conversation->id
            );
        }

        $conversation->load([
            'userOne:id,name,email,profile_photo_path,last_seen_at',
            'userTwo:id,name,email,profile_photo_path,last_seen_at',
            'service:id,name',
        ]);

        return response()->json($conversation);
    }

    public function myConversations(): \Illuminate\Http\JsonResponse {
        $userId = Auth::id();
        $user   = User::find($userId);
        $user->update(['last_seen_at' => now()]);

        $conversations = Conversation::where(function ($q) use ($userId) {
                $q->where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId);
            })
            ->where('user_one_id', '!=', 1)
            ->where('user_two_id', '!=', 1)
            ->with([
                'latestMessage.sender',
                'userOne:id,name,email,profile_photo_path,last_seen_at,fcm_token',
                'userTwo:id,name,email,profile_photo_path,last_seen_at,fcm_token'
            ])
            ->withCount([
                'messages as unread_count' => function ($q) use ($userId) {
                    $q->where('sender_id', '!=', $userId)
                    ->whereNull('read_at');
                }
            ])
            ->latest('updated_at')
            ->get()
            ->map(function ($conv) use ($userId) {
                $conv->other_user = $conv->user_one_id == $userId
                    ? $conv->userTwo
                    : $conv->userOne;

                return $conv;
        });

        return response()->json($conversations);
    }

    public function getMessages(int $conversationId): \Illuminate\Http\JsonResponse
    {
        $conv = Conversation::with([
            'service',
        ])->find($conversationId);

        if (!$conv) return response()->json(['message' => 'Conversation introuvable'], 404);

        $userId = Auth::id();
        $user   = User::find($userId);
        $user->update(['last_seen_at' => now()]);

        if (!$this->isMember($conv, $userId)) return response()->json(['message' => 'Non autorisé'], 403);

        $otherUser = $conv->user_one_id === $userId
            ? User::find($conv->user_two_id)
            : User::find($conv->user_one_id);

        // ── Pagination : 60 derniers messages par défaut ────────────────────
        $limit  = (int) request()->get('limit', 60);
        $before = request()->get('before'); // ID pivot pour la pagination infinie

        $query = Message::with(['sender:id,name,profile_photo_path', 'replyTo.sender:id,name,profile_photo_path'])
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->limit(min($limit, 100)); // cap à 100 pour éviter les abus

        if ($before) {
            $pivot = Message::find($before);
            if ($pivot) {
                $query->where('created_at', '<', $pivot->created_at);
            }
        }

        $messages = $query->get()->reverse()->values();

        // Marquer comme lus
        Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'id'              => $conv->id,
            'messages'        => $messages,
            'other_user'      => $otherUser,
            'service'         => $conv->service,
            'service_name'    => $conv->service_name,
            'entreprise_name' => $conv->entreprise_name,
            'has_more'        => $messages->count() >= $limit,
        ]);
    }

    // =========================================================================
    //  STATUT EN LIGNE
    // =========================================================================

    public function updateOnlineStatus(): \Illuminate\Http\JsonResponse
    {
        $user = User::findOrFail(Auth::id());
        $user->update(['last_seen_at' => now()]);

        $conversations = Conversation::where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->get();

        foreach ($conversations as $conv) {
            $otherUserId = $conv->user_one_id === $user->id ? $conv->user_two_id : $conv->user_one_id;
            if ($otherUserId) {
                $this->triggerPusher('private-user.' . $otherUserId, 'user-status', [
                    'user_id'      => $user->id,
                    'is_online'    => true,
                    'last_seen'    => $user->last_seen_at,
                    'last_seen_at' => $user->last_seen_at,
                ]);
            }
        }

        return response()->json(['message' => 'Statut mis à jour', 'last_seen_at' => $user->last_seen_at]);
    }

    public function checkOnlineStatus(int $userId): \Illuminate\Http\JsonResponse
    {
        $user = User::find($userId);
        if (!$user) return response()->json(['message' => 'Utilisateur introuvable'], 404);

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

    // =========================================================================
    //  MODIFIER / SUPPRIMER UN MESSAGE
    // =========================================================================

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $request->validate(['content' => 'required|string|max:5000']);

        $message = Message::find($id);
        if (!$message) return response()->json(['message' => 'Message introuvable'], 404);
        if ((int) $message->sender_id !== (int) Auth::id()) return response()->json(['message' => 'Non autorisé'], 403);
        if ($message->type !== 'text') return response()->json(['message' => 'Seuls les messages texte peuvent être modifiés'], 422);
        if (now()->diffInMinutes($message->created_at) > 15) {
            return response()->json(['message' => 'Délai de modification dépassé (15 minutes max)'], 422);
        }

        $message->content = $request->content;
        $message->edited  = true;
        $message->save();
        $message->load(['sender:id,name,profile_photo_path', 'replyTo']);

        return response()->json(['message' => 'Message modifié', 'data' => $message]);
    }

    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $message = Message::find($id);
        if (!$message) return response()->json(['message' => 'Message introuvable'], 404);
        if ((int) $message->sender_id !== (int) Auth::id()) return response()->json(['message' => 'Non autorisé'], 403);

        $this->deleteFile($message->file_path);
        $message->delete();

        return response()->json(['message' => 'Message supprimé']);
    }

    public function destroyConversation(int $id): \Illuminate\Http\JsonResponse
    {
        $conv = Conversation::find($id);
        if (!$conv) return response()->json(['message' => 'Conversation introuvable'], 404);

        $userId = Auth::id();
        if (!$this->isMember($conv, $userId)) return response()->json(['message' => 'Non autorisé'], 403);

        // Supprimer les fichiers média liés
        Message::where('conversation_id', $id)->whereNotNull('file_path')->get()
            ->each(fn($msg) => $this->deleteFile($msg->file_path));

        Message::where('conversation_id', $id)->delete();
        $conv->delete();

        $otherUserId = $conv->user_one_id === $userId ? $conv->user_two_id : $conv->user_one_id;
        if ($otherUserId) {
            $this->triggerPusher('private-user.' . $otherUserId, 'conversation-deleted', ['conversation_id' => $id]);
        }

        return response()->json(['message' => 'Conversation supprimée', 'success' => true]);
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function isMember(Conversation $conv, ?int $userId): bool
    {
        return $conv->user_one_id === $userId || $conv->user_two_id === $userId;
    }

    /**
     * Upload un fichier sur le disque public local.
     * Retourne le chemin relatif (ex: messages/12/image/uuid.jpg).
     */
    private function uploadFile($file, int $convId, string $type): string {
        $extension  = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $folderPath = "messages/{$convId}/{$type}";
        $fileName   = Str::uuid() . '.' . $extension;

        try {
            $stored = Storage::disk('public')->putFileAs($folderPath, $file, $fileName);
            if (!$stored) {
                throw new \Exception('putFileAs a retourné false');
            }
            Log::info('Fichier uploadé', ['path' => $stored]);
            return $stored;
        } catch (\Exception $e) {
            Log::error('Erreur upload fichier: ' . $e->getMessage());
            throw new \Exception('Erreur upload fichier: ' . $e->getMessage());
        }
    }

    private const STORAGE_BASE_URL = 'https://careasy.cap-epac.bj/api/storage';

    private function getFileUrl(?string $filePath): ?string
    {
        if (!$filePath) return null;
        // Déjà une URL complète (legacy) → retourner telle quelle
        if (filter_var($filePath, FILTER_VALIDATE_URL)) return $filePath;
        return self::STORAGE_BASE_URL . '/' . ltrim($filePath, '/');
    }

    /**
     * Supprime un fichier du disque public.
     */
    private function deleteFile(?string $filePath): void
    {
        if (!$filePath) return;
        try {
            Storage::disk('public')->delete($filePath);
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
