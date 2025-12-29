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

class MessageController extends Controller{
    public function getMessages($convId){
        $conv = Conversation::with(['messages.sender'])
            ->find($convId);

        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        // Autorisation : faire partie de la conversation
        if (! $this->isMember($conv, Auth::id())) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Marquer comme lus (optionnel ici, on le fait via markAsRead dédié)
        return response()->json($conv);
    }

    public function sendMessage(Request $request, $convId){
        $conv = Conversation::find($convId);
        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $userId = Auth::id();
        if (! $this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        /* -------- validation -------- */
        $rules = [
            'type'      => 'required|in:text,image,video,vocal',
            'content'   => 'required_if:type,text|string|nullable',
            'file'      => 'required_unless:type,text|file',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        /* -------- upload fichier -------- */
        $filePath = null;
        if ($request->hasFile('file')) {
            $disk = 'public';
            $dir  = match ($request->type) {
                'image' => 'uploads/messages/images',
                'video' => 'uploads/messages/videos',
                'vocal' => 'uploads/messages/vocals',
            };
            $file = $request->file('file');

            // sécurité : types & tailles

            $maxSize = match ($request->type) {
                'image' => 5 * 1024,   // 5 Mo
                'video' => 25 * 1024,  // 25 Mo
                'vocal' => 5 * 1024,   // 5 Mo
            };
            if ($file->getSize() > $maxSize * 1024) {
                return response()->json(['message' => 'Fichier trop lourd'], 413);
            }
            $filePath = $file->store($dir, $disk);
        }

        /* -------- création message -------- */
        DB::beginTransaction();
        try {
            $msg = Message::create([
                'conversation_id' => $conv->id,
                'sender_id'       => $userId,
                'content'         => $request->input('content'),
                'type'            => $request->type,
                'file_path'       => $filePath,
                'latitude'        => $request->latitude,
                'longitude'       => $request->longitude,
            ]);
            $conv->touch(); // updated_at
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // suppression fichier si uploadé
            if ($filePath) Storage::disk('public')->delete($filePath);
            Log::error('sendMessage error', ['e' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur interne'], 500);
        }

        return response()->json($msg->load('sender'), 201);
    }

    // MARQUER COMME LUS
    public function markAsRead($convId){
        $conv = Conversation::find($convId);
        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }
        $userId = Auth::id();
        if (! $this->isMember($conv, $userId)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $updated = Message::where('conversation_id', $convId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Messages marqués lus', 'count' => $updated]);
    }

    //LISTE MES CONVERSATIONS (avec unreadCount)

    public function myConversations(){
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

    public function startConversation(Request $request){
        $request->validate(['receiver_id' => 'nullable|exists:users,id']);
        $userId = Auth::id();
        $recvId = $request->receiver_id;

        // anonyme
        if (! $userId && $recvId) {
            $conv = Conversation::create(['user_one_id' => $recvId, 'user_two_id' => null]);
            return response()->json($conv, 201);
        }

        // authentifié
        if ($userId && $recvId) {
            $conv = Conversation::where(fn($q) => $q->where('user_one_id', $userId)->where('user_two_id', $recvId))
                ->orWhere(fn($q) => $q->where('user_one_id', $recvId)->where('user_two_id', $userId))
                ->first();
            if (! $conv) {
                $conv = Conversation::create(['user_one_id' => $userId, 'user_two_id' => $recvId]);
            }
            return response()->json($conv);
        }

        return response()->json(['message' => 'Receiver required'], 422);
    }

    private function isMember(Conversation $conv, ?int $userId): bool{
        return $conv->user_one_id === $userId || $conv->user_two_id === $userId;
    }
}