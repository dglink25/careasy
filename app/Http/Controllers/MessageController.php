<?php
// app/Http/Controllers/MessageController.php - VERSION FINALE CORRIGÉE

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller{
    
    /**
     * Récupérer toutes les conversations du prestataire connecté
     */
    public function myConversations() {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        Log::info('📋 myConversations - User ID: ' . $user->id);

        // Récupérer les conversations où l'utilisateur est user_one OU user_two
        $conversations = Conversation::where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->with([
                'messages' => function($query) {
                    $query->orderBy('created_at', 'desc')->limit(1);
                },
                'messages.sender',
                'userOne',
                'userTwo'
            ])
            ->orderBy('updated_at', 'desc')
            ->get();

        Log::info('📋 Conversations trouvées: ' . $conversations->count());

        // Ajouter des infos sur l'autre utilisateur
        $conversations = $conversations->map(function($conv) use ($user) {
            // Déterminer qui est l'autre utilisateur
            if ($conv->user_one_id === $user->id) {
                // Je suis user_one, l'autre est user_two
                if ($conv->user_two_id === null) {
                    $conv->other_user = (object)[
                        'id' => null,
                        'name' => 'Visiteur Anonyme',
                        'email' => null
                    ];
                    $conv->other_user_id = null;
                    $conv->is_anonymous = true;
                } else {
                    $conv->other_user = $conv->userTwo;
                    $conv->other_user_id = $conv->user_two_id;
                    $conv->is_anonymous = false;
                }
            } else {
                // Je suis user_two, l'autre est user_one
                $conv->other_user = $conv->userOne;
                $conv->other_user_id = $conv->user_one_id;
                $conv->is_anonymous = false;
            }
            
            // Compter les messages non lus
            $conv->unread_count = Message::where('conversation_id', $conv->id)
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->count();
            
            return $conv;
        });

        return response()->json($conversations);
    }

    /**
     * Créer ou récupérer une conversation
     */
    public function startConversation(Request $request) {
        $request->validate([
            'receiver_id' => 'nullable|exists:users,id'
        ]);

        $userId = Auth::id();
        $receiverId = $request->receiver_id;

        Log::info('🔄 startConversation - userId: ' . $userId . ', receiverId: ' . $receiverId);

        // ✅ CAS 1: Utilisateur anonyme contacte un prestataire
        if (!$userId && $receiverId) {
            Log::info('✅ Création conversation anonyme -> prestataire');
            $conversation = Conversation::create([
                'user_one_id' => $receiverId,  // Prestataire
                'user_two_id' => null,         // Anonyme
            ]);
            Log::info('✅ Conversation créée ID: ' . $conversation->id);
            return response()->json($conversation);
        }

        // ❌ CAS INVALIDE: Ni sender ni receiver
        if (!$userId && !$receiverId) {
            return response()->json(['message' => 'Receiver required for anonymous'], 422);
        }

        // ✅ CAS 2: Utilisateur authentifié contacte un autre utilisateur
        if ($userId && $receiverId) {
            Log::info('✅ Recherche conversation entre User ' . $userId . ' et User ' . $receiverId);
            
            // Chercher conversation existante (dans les deux sens)
            $conversation = Conversation::where(function($query) use ($userId, $receiverId) {
                $query->where('user_one_id', $userId)
                      ->where('user_two_id', $receiverId);
            })->orWhere(function($query) use ($userId, $receiverId) {
                $query->where('user_one_id', $receiverId)
                      ->where('user_two_id', $userId);
            })->first();

            // Si pas trouvée, créer nouvelle conversation
            if (!$conversation) {
                Log::info('✅ Création nouvelle conversation');
                $conversation = Conversation::create([
                    'user_one_id' => $userId,      // Celui qui initie
                    'user_two_id' => $receiverId   // Le destinataire
                ]);
                Log::info('✅ Conversation créée ID: ' . $conversation->id);
            } else {
                Log::info('✅ Conversation existante trouvée ID: ' . $conversation->id);
            }

            return response()->json($conversation);
        }

        // ❌ CAS INVALIDE: Utilisateur authentifié sans destinataire
        return response()->json(['message' => 'Receiver ID required'], 422);
    }

    /**
     * Envoyer un message
     */
    public function sendMessage(Request $request, $conversationId) {
        $request->validate([
            'content' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric'
        ]);

        $conversation = Conversation::findOrFail($conversationId);
        $senderId = Auth::id(); // null si anonyme

        Log::info('📤 sendMessage - Conversation: ' . $conversationId . ', Sender: ' . ($senderId ?? 'anonyme'));

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $senderId,  // Peut être null pour anonyme
            'content' => $request->content,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        // Mettre à jour le timestamp de la conversation
        $conversation->touch();

        // Charger les relations pour la réponse
        $msg->load('sender');

        Log::info('✅ Message créé ID: ' . $msg->id . ', sender_id: ' . ($msg->sender_id ?? 'null'));

        return response()->json($msg);
    }

    /**
     * Récupérer les messages d'une conversation
     */
    public function getMessages($conversationId) {
        $conv = Conversation::with([
            'messages' => function($query) {
                $query->orderBy('created_at', 'asc');
            },
            'messages.sender',
            'userOne',
            'userTwo'
        ])->findOrFail($conversationId);
        
        Log::info('📥 getMessages - Conversation: ' . $conversationId . ', Messages: ' . $conv->messages->count());
        
        return response()->json($conv);
    }

    /**
     * Marquer les messages comme lus
     */
    public function markAsRead($conversationId) {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $conversation = Conversation::findOrFail($conversationId);
        
        // Marquer tous les messages de l'autre utilisateur comme lus
        $updated = Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        Log::info('✅ Messages marqués comme lus: ' . $updated);

        return response()->json(['message' => 'Messages marqués comme lus', 'count' => $updated]);
    }
}