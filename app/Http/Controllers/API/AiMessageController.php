<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiMessageController extends Controller{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|integer',
            'content'         => 'nullable|string',
            'type'            => 'nullable|string|in:text,image,video,vocal',
            'ai_metadata'     => 'nullable|array',
            'latitude'        => 'nullable|numeric',
            'longitude'       => 'nullable|numeric',
        ]);

        $message = Message::create([
            'conversation_id' => $validated['conversation_id'],
            'sender_id'       => null,
            'content'         => $validated['content'] ?? null,
            'type'            => $validated['type'] ?? 'text',
            'ai_metadata'     => $validated['ai_metadata'] ?? null,
            'latitude'        => $validated['latitude'] ?? null,
            'longitude'       => $validated['longitude'] ?? null,
        ]);

        return response()->json(['id' => $message->id, 'created' => true], 201);
    }

    public function history(Request $request, int $id)
    {
        $limit    = (int) $request->input('limit', 10);
        $messages = Message::where('conversation_id', $id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id','sender_id','content','type','ai_metadata','created_at'])
            ->reverse()->values();
        return response()->json(['data' => $messages]);
    }
}