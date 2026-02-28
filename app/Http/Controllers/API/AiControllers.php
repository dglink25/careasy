<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\LocationBenin;
use App\Models\AiLog;
use App\Models\AiSession;
use App\Models\AiFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class AiMessageController extends Controller{
  
    public function store(Request $request){
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
            'sender_id'       => null,   // null = message IA
            'content'         => $validated['content'] ?? null,
            'type'            => $validated['type'] ?? 'text',
            'ai_metadata'     => $validated['ai_metadata'] ?? null,
            'latitude'        => $validated['latitude']  ?? null,
            'longitude'       => $validated['longitude'] ?? null,
        ]);

        return response()->json(['id' => $message->id, 'created' => true], 201);
    }

    public function history(Request $request, int $id) {
        $limit = (int) $request->input('limit', 10);

        $messages = Message::where('conversation_id', $id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'sender_id', 'content', 'type', 'ai_metadata', 'created_at'])
            ->reverse()
            ->values();

        return response()->json(['data' => $messages]);
    }
}

class AiLocationController extends Controller{

    public function search(Request $request){
        $q     = $request->input('q', '');
        $limit = (int) $request->input('limit', 5);

        if (empty($q)) {
            return response()->json(['data' => []], 200);
        }

        $results = DB::table('locations_benin')
            ->where('arrondissement', 'like', "%{$q}%")
            ->orWhere('commune',      'like', "%{$q}%")
            ->orWhere('departement',  'like', "%{$q}%")
            ->limit($limit)
            ->get();

        return response()->json(['data' => $results]);
    }


    public function communes() {
        $communes = DB::table('locations_benin')
            ->distinct()
            ->orderBy('commune')
            ->pluck('commune');

        return response()->json(['communes' => $communes]);
    }
}

