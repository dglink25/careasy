<?php
// app/Http/Controllers/MessageController.php - VERSION COMPLÈTE MISE À JOUR

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller{
    
    /**
     * Récupérer toutes les conversations du prestataire connecté
     */
    public function myConversations() {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

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
                    $conv->is_anonymous = true;
                } else {
                    $conv->other_user = $conv->userTwo;
                    $conv->is_anonymous = false;
                }
            } else {
                // Je suis user_two, l'autre est user_one
                $conv->other_user = $conv->userOne;
                $conv->is_anonymous = false;
            }
            
            $conv->unread_count = 0; // À implémenter plus tard si besoin
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

        $user = Auth::id();
        $receiver = $request->receiver_id;

        // Anonymous to connected user
        if (!$user && !$receiver) {
            return response()->json(['message' => 'Receiver required for anonymous'], 422);
        }

        // Anonymous visitor
        if (!$user) {
            $conversation = Conversation::create([
                'user_one_id' => $receiver,
                'user_two_id' => null,
            ]);
            return response()->json($conversation);
        }

        // Logged user contacting another
        // Vérifier si une conversation existe déjà
        $conversation = Conversation::where(function($query) use ($user, $receiver) {
            $query->where('user_one_id', $user)
                  ->where('user_two_id', $receiver);
        })->orWhere(function($query) use ($user, $receiver) {
            $query->where('user_one_id', $receiver)
                  ->where('user_two_id', $user);
        })->first();

        // Si pas de conversation, en créer une
        if (!$conversation) {
            $conversation = Conversation::create([
                'user_one_id' => $user,
                'user_two_id' => $receiver
            ]);
        }

        return response()->json($conversation);
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
        $senderId = Auth::id(); // null = anonymous

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $senderId,
            'content' => $request->content,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        // Mettre à jour le timestamp de la conversation
        $conversation->touch();

        // Charger les relations pour la réponse
        $msg->load('sender');

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
            'messages.sender'
        ])->findOrFail($conversationId);
        
        return response()->json($conv);
    }

    /**
     * Marquer les messages comme lus (optionnel pour plus tard)
     */
    public function markAsRead($conversationId) {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $conversation = Conversation::findOrFail($conversationId);
        
        // Marquer tous les messages de l'autre utilisateur comme lus
        Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Messages marqués comme lus']);
    }
}