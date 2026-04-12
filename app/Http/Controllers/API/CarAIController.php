<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\API\AiServiceController;


class CarAIController extends Controller
{
    /**
     * URL du micro-service Python CarAI.
     * Configurable via CARAI_SERVICE_URL dans .env
     */
    private string $caraiUrl;

    public function __construct()
    {
        $this->caraiUrl = env('CARAI_SERVICE_URL', 'https://careasyaiservice.onrender.com');
    }

    // ══════════════════════════════════════════════════════════════════════
    //  POST /api/carai/conversations/start
    // ══════════════════════════════════════════════════════════════════════

    public function startConversation(Request $request)
    {
        $user = Auth::user();

        $conv = Conversation::firstOrCreate(
            [
                'user_one_id' => $user->id,
                'user_two_id' => null,
            ],
            [
                'service_name'    => 'CarAI',
                'entreprise_name' => 'CarEasy Assistant',
            ]
        );

        return response()->json([
            'conversation_id' => $conv->id,
            'is_new'          => $conv->wasRecentlyCreated,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  POST /api/carai/chat
    // ══════════════════════════════════════════════════════════════════════

    public function chat(Request $request)
    {
        $validated = $request->validate([
            'message'         => 'required|string|max:2000',
            'conversation_id' => 'required|integer',
            'latitude'        => 'nullable|numeric|between:-90,90',
            'longitude'       => 'nullable|numeric|between:-180,180',
            'language'        => 'nullable|string|max:10',
        ]);

        $user = Auth::user();
        $conv = $this->resolveConversation($validated['conversation_id'], $user);
        $convKey = 'carai-' . $conv->id;

        // 1. Sauvegarder le message utilisateur
        $userMessage = Message::create([
            'conversation_id' => $conv->id,
            'sender_id'       => $user->id,
            'content'         => $validated['message'],
            'type'            => 'text',
            'ai_metadata'     => [
                'role'      => 'user',
                'latitude'  => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ],
        ]);

        // 2. Appeler le service Python CarAI
        try {
            $payload = [
                'message'         => $validated['message'],
                'conversation_id' => $convKey,
                'user_id'         => $user->id,
                'latitude'        => $validated['latitude'] ?? null,
                'longitude'       => $validated['longitude'] ?? null,
                'language'        => $validated['language'] ?? 'fr',
            ];

            $response = Http::timeout(45)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->caraiUrl}/chat", $payload);

            if (!$response->successful()) {
                Log::error('CarAI service error', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'url'     => $this->caraiUrl . '/chat',
                ]);
                return $this->fallbackResponse($conv, $userMessage);
            }

            $aiData = $response->json();

            if (empty($aiData['reply'])) {
                Log::error('CarAI response missing reply field', ['response' => $aiData]);
                return $this->fallbackResponse($conv, $userMessage);
            }

        } catch (\Exception $e) {
            Log::error('CarAI service unreachable', [
                'error' => $e->getMessage(),
                'url'   => $this->caraiUrl . '/chat',
            ]);
            return $this->fallbackResponse($conv, $userMessage);
        }

        // 3. Sauvegarder la réponse IA
        $aiMessage = Message::create([
            'conversation_id' => $conv->id,
            'sender_id'       => 1,
            'content'         => $aiData['reply'],
            'type'            => 'text',
            'ai_metadata'     => [
                'role'        => 'assistant',
                'intent'      => $aiData['intent'] ?? null,
                'language'    => $aiData['language'] ?? 'fr',
                'services'    => $aiData['services'] ?? [],
                'map_url'     => $aiData['map_url'] ?? null,
                'itinerary'   => $aiData['itinerary'] ?? null,
                'suggestions' => $aiData['suggestions'] ?? [],
            ],
        ]);

        // 4. Logger
        $this->logInteraction($userMessage->id, $validated['message'], $aiData, $user->id);

        return response()->json([
            'success'     => true,
            'message_id'  => $aiMessage->id,
            'reply'       => $aiData['reply'],
            'services'    => $aiData['services'] ?? [],
            'map_url'     => $aiData['map_url'] ?? null,
            'itinerary'   => $aiData['itinerary'] ?? null,
            'intent'      => $aiData['intent'] ?? null,
            'language'    => $aiData['language'] ?? 'fr',
            'suggestions' => $aiData['suggestions'] ?? [],
            'confidence'  => $aiData['confidence'] ?? 1.0,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  GET /api/carai/conversations/{id}/messages
    // ══════════════════════════════════════════════════════════════════════

    public function history(Request $request, int $conversationId)
    {
        $user = Auth::user();

        $conv = Conversation::where('id', $conversationId)
            ->where(function ($q) use ($user) {
                $q->where('user_one_id', $user->id)
                  ->orWhere('user_two_id', $user->id);
            })
            ->first();

        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        $limit    = (int) $request->input('limit', 30);
        $messages = Message::where('conversation_id', $conversationId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($m) => [
                'id'          => $m->id,
                'role'        => $m->sender_id ? 'user' : 'assistant',
                'content'     => $m->content,
                'ai_metadata' => $m->ai_metadata,
                'created_at'  => $m->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $messages]);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  DELETE /api/carai/conversations/{id}
    // ══════════════════════════════════════════════════════════════════════

    public function clearHistory(int $conversationId)
    {
        $user = Auth::user();

        $conv = Conversation::where('id', $conversationId)
            ->where('user_one_id', $user->id)
            ->first();

        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable'], 404);
        }

        Message::where('conversation_id', $conversationId)->delete();

        try {
            Http::timeout(5)->delete("{$this->caraiUrl}/conversation/carai-{$conversationId}");
        } catch (\Exception $e) {
            Log::warning('Impossible de supprimer le cache Redis CarAI', ['error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Historique supprimé avec succès']);
    }

    public function nearby(Request $request)
    {
        $validated = $request->validate([
            'lat'     => 'required|numeric|between:-90,90',
            'lng'     => 'required|numeric|between:-180,180',
            'domaine' => 'nullable|string|max:100',
            'radius'  => 'nullable|numeric|min:1|max:100',
        ]);

        $cacheKey = 'nearby:'
            . round($validated['lat'], 2) . ','
            . round($validated['lng'], 2) . ':'
            . ($validated['domaine'] ?? 'all');

        $data = Cache::remember($cacheKey, 300, function () use ($validated, $request) {
            // FIX: Appel direct au contrôleur Laravel au lieu d'un appel HTTP
            $aiRequest = Request::create('/api/ai/services/nearby', 'GET', [
                'lat'     => $validated['lat'],
                'lng'     => $validated['lng'],
                'radius'  => $validated['radius'] ?? 15,
                'limit'   => 8,
                'domaine' => $validated['domaine'] ?? null,
            ]);

            $aiController = new AiServiceController();
            $response = $aiController->nearby($aiRequest);

            return $response->getData(true);
        });

        return response()->json($data);
    }


    private function resolveConversation(int $convId, $user): Conversation
    {
        $conv = Conversation::find($convId);

        if (!$conv) {
            $conv = Conversation::create([
                'user_one_id'     => $user->id,
                'user_two_id'     => null,
                'service_name'    => 'CarAI',
                'entreprise_name' => 'CarEasy Assistant',
            ]);
        }

        return $conv;
    }

    private function fallbackResponse(Conversation $conv, Message $userMessage)
    {
        $fallback = "Je suis momentanément indisponible. Réessaie dans quelques secondes ou contacte le support CarEasy.";

        $aiMessage = Message::create([
            'conversation_id' => $conv->id,
            'sender_id'       => null,
            'content'         => $fallback,
            'type'            => 'text',
            'ai_metadata'     => ['role' => 'assistant', 'is_fallback' => true],
        ]);

        return response()->json([
            'success'     => false,
            'message_id'  => $aiMessage->id,
            'reply'       => $fallback,
            'services'    => [],
            'suggestions' => ['Réessayer', 'Trouver un garage', 'Vulcanisateur'],
        ], 503);
    }

    private function logInteraction(int $messageId, string $input, array $aiData, int $userId): void
    {
        try {
            DB::table('ai_logs')->insert([
                'message_id'        => $messageId,
                'ai_input'          => mb_substr($input, 0, 5000),
                'ai_output'         => mb_substr($aiData['reply'] ?? '', 0, 5000),
                'detected_intent'   => $aiData['intent'] ?? null,
                'detected_language' => $aiData['language'] ?? 'fr',
                'model_version'     => 'carai-v9',
                'created_at'        => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('ai_logs insert failed', ['error' => $e->getMessage()]);
        }
    }
}