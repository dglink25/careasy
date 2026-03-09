<?php
// app/Http/Controllers/BroadcastingController.php
// VERSION 3 — Broadcast::auth() retourne null dans Laravel 12, on signe manuellement

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BroadcastingController extends Controller
{
    public function authenticate(Request $request)
    {
        if (!$request->user()) {
            return response()->json(['error' => 'Unauthenticated'], 403);
        }

        $channelName = $request->input('channel_name');
        $socketId    = $request->input('socket_id');

        Log::info('Broadcasting auth', [
            'user_id' => $request->user()->id,
            'channel' => $channelName,
            'socket'  => $socketId,
        ]);

        if (!$channelName || !$socketId) {
            return response()->json(['error' => 'Missing channel_name or socket_id'], 400);
        }

        // Vérifier que l'utilisateur est autorisé sur ce canal
        if (!$this->isAuthorized($request->user(), $channelName)) {
            Log::warning('Broadcasting auth: accès refusé', [
                'user_id' => $request->user()->id,
                'channel' => $channelName,
            ]);
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Signer le token Pusher manuellement
        $key    = config('broadcasting.connections.pusher.key');
        $secret = config('broadcasting.connections.pusher.secret');

        if (!$key || !$secret) {
            Log::error('Pusher key/secret manquants dans config');
            return response()->json(['error' => 'Pusher not configured'], 500);
        }

        $signature = hash_hmac('sha256', "{$socketId}:{$channelName}", $secret);
        $auth      = "{$key}:{$signature}";

        Log::info('Broadcasting auth OK', ['auth' => substr($auth, 0, 20) . '...']);

        return response()->json(['auth' => $auth]);
    }

    /**
     * Vérifie si l'utilisateur peut accéder au canal demandé.
     */
    private function isAuthorized($user, string $channelName): bool
    {
        // Canal privé utilisateur : private-user.{userId}
        if (preg_match('/^private-user\.(\d+)$/', $channelName, $m)) {
            return (int) $user->id === (int) $m[1];
        }

        // Canal privé conversation : private-conversation.{conversationId}
        if (preg_match('/^private-conversation\.(\d+)$/', $channelName, $m)) {
            $conv = \App\Models\Conversation::find((int) $m[1]);
            if (!$conv) return false;
            return (int) $conv->user_one_id === (int) $user->id
                || (int) $conv->user_two_id === (int) $user->id;
        }

        return false;
    }
}