<?php

namespace App\Http\Controllers;


use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller{
    // Create or fetch a conversation
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
        $conversation = Conversation::firstOrCreate([
            'user_one_id' => $user,
            'user_two_id' => $receiver
        ]);
        return response()->json($conversation);
    }


    // Send a message
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


        return response()->json($msg);
    }

    // Fetch conversation messages
    public function getMessages($conversationId) {
        $conv = Conversation::with('messages.sender')->findOrFail($conversationId);
        return response()->json($conv);
    }
}