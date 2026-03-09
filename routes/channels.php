<?php
// routes/channels.php
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canal privé utilisateur : private-user.{userId}
| Reçoit : new-message, messages-read, user-status, typing-indicator
|--------------------------------------------------------------------------
*/
Broadcast::channel('user.{userId}', function ($user, $userId) {
    \Log::info("Auth canal user.{$userId} par user #{$user->id}");
    return (int) $user->id === (int) $userId;
});

/*
|--------------------------------------------------------------------------
| Canal privé conversation : private-conversation.{conversationId}
| Reçoit : message-sent, typing-indicator
|--------------------------------------------------------------------------
*/
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    \Log::info("Auth canal conversation.{$conversationId} par user #{$user->id}");

    // Vérifier que l'utilisateur fait partie de la conversation
    $conv = \App\Models\Conversation::find($conversationId);
    if (!$conv) return false;

    $isMember = (int) $conv->user_one_id === (int) $user->id
             || (int) $conv->user_two_id === (int) $user->id;

    return $isMember ? ['id' => $user->id, 'name' => $user->name] : false;
});