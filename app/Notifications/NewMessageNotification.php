<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;
use NotificationChannels\Fcm\Resources\AndroidConfig;
use NotificationChannels\Fcm\Resources\AndroidNotification;
use NotificationChannels\Fcm\Resources\ApnsConfig;

class NewMessageNotification extends Notification
{
    protected Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Canaux utilisés
     */
    public function via($notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (!empty($notifiable->fcm_token)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    /**
     * Nom de l'événement broadcast
     */
    public function broadcastAs(): string
    {
        return 'new-message';
    }

    /**
     * Broadcast temps réel
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type'            => 'message',
            'title'           => 'Nouveau message',
            'body'            => substr($this->message->content ?? '', 80),
            'sender_id'       => $this->message->sender_id,
            'sender_name'     => $this->message->sender?->name ?? 'Quelqu\'un',
            'conversation_id' => $this->message->conversation_id,
            'message_id'      => $this->message->id,
            'url'             => '/messages/' . $this->message->conversation_id,
        ]);
    }

    /**
     * Notification stockée en base
     */
    public function toDatabase($notifiable): array
    {
        return [
            'type'            => 'message',
            'title'           => 'Nouveau message de ' . ($this->message->sender?->name ?? 'Inconnu'),
            'body'            => substr($this->message->content ?? '', 80),
            'sender_id'       => $this->message->sender_id,
            'conversation_id' => $this->message->conversation_id,
            'url'             => '/messages/' . $this->message->conversation_id,
        ];
    }

    /**
     * Notification Firebase Cloud Messaging
     */
    public function toFcm($notifiable): FcmMessage
    {
        $senderName = $this->message->sender?->name ?? 'Nouveau message';

        $body = substr($this->message->content ?? '', 100);

        if (empty($body)) {
            $body = match ($this->message->type) {
                'image'    => 'Image',
                'video'    => 'Vidéo',
                'vocal'    => 'Message vocal',
                'document' => 'Document',
                'location' => 'Localisation',
                default    => 'Nouveau message',
            };
        }

        return (new FcmMessage(
            notification: new FcmNotification(
                title: $senderName,
                body: $body
            )
        ))
        ->data([
            'type'            => 'message',
            'conversation_id' => (string) $this->message->conversation_id,
            'sender_id'       => (string) $this->message->sender_id,
            'sender_name'     => $senderName,
            'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
        ])
        ->android(
            AndroidConfig::create()
                ->notification(
                    AndroidNotification::create()
                        ->channelId('high_importance_channel')
                        ->sound('default')
                        ->clickAction('FLUTTER_NOTIFICATION_CLICK')
                )
        )
        ->apns(
            ApnsConfig::create()
                ->payload([
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ])
        );
    }
}