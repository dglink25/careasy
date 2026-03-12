<?php
namespace App\Notifications;

use App\Models\Message;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification
{
    protected Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastAs(): string
    {
        return 'new-message';
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type'            => 'message',
            'title'           => '💬 Nouveau message',
            'body'            => substr($this->message->content ?? '', 0, 80),
            'sender_id'       => $this->message->sender_id,
            'sender_name'     => $this->message->sender?->name ?? 'Quelqu\'un',
            'conversation_id' => $this->message->conversation_id,
            'message_id'      => $this->message->id,
            'url'             => '/messages/' . $this->message->conversation_id,
        ]);
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'            => 'message',
            'title'           => '💬 Nouveau message de ' . ($this->message->sender?->name ?? 'Inconnu'),
            'body'            => substr($this->message->content ?? '', 0, 80),
            'conversation_id' => $this->message->conversation_id,
            'url'             => '/messages/' . $this->message->conversation_id,
        ];
    }
}