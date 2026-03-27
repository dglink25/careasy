<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AiFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiLogController extends Controller
{
    public function store(Request $request)
    {
        DB::table('ai_logs')->insert([
            'message_id'        => $request->input('message_id'),
            'ai_input'          => substr($request->input('ai_input',''), 0, 5000),
            'ai_output'         => substr($request->input('ai_output',''), 0, 5000),
            'detected_intent'   => $request->input('detected_intent'),
            'detected_language' => $request->input('detected_language','fr'),
            'confidence'        => $request->input('confidence'),
            'model_version'     => $request->input('model_version','gpt-4o'),
            'created_at'        => now(),
        ]);
        return response()->json(['logged' => true], 201);
    }

    public function saveSession(Request $request)
    {
        $data = [
            'conversation_id'   => $request->input('conversation_id'),
            'user_id'           => $request->input('user_id'),
            'detected_language' => $request->input('detected_language','fr'),
            'current_intent'    => $request->input('current_intent'),
            'context'           => json_encode($request->input('context',[])),
            'updated_at'        => now(),
        ];
        DB::table('ai_sessions')->updateOrInsert(
            ['conversation_id' => $data['conversation_id'], 'user_id' => $data['user_id']],
            array_merge($data, ['created_at' => now()])
        );
        return response()->json(['saved' => true]);
    }

    public function feedback(Request $request)
    {
        $validated = $request->validate([
            'message_id'        => 'required|exists:messages,id',
            'rating'            => 'required|integer|min:1|max:5',
            'comment'           => 'nullable|string',
            'is_helpful'        => 'nullable|boolean',
            'language_detected' => 'nullable|string',
            'domain_detected'   => 'nullable|string',
        ]);
        AiFeedback::create($validated);
        return response()->json(['saved' => true], 201);
    }
}