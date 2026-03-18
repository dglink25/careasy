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

    public function via($notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (!empty($notifiable->fcm_token)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'new-message';
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type'            => 'message',
            'title'           => '💬 Message de ' . ($this->message->sender?->name ?? 'Quelqu\'un'),
            // ✅ CORRECTION DU BUG : substr(str, 0, 80) — ton code avait substr(str, 80) = coupe les 80 premiers
            'body'            => substr($this->message->content ?? '', 0, 80),
            'sender_id'       => $this->message->sender_id,
            'sender_name'     => $this->message->sender?->name ?? 'Quelqu\'un',
            'conversation_id' => $this->message->conversation_id,
            'message_id'      => $this->message->id,
            // ✅ URL simple — le frontend utilise conversation_id pour ouvrir le modal
            'url'             => '/messages',
        ]);
    }

    public function toDatabase($notifiable): array
    {
        $senderName = $this->message->sender?->name ?? 'Inconnu';
        // ✅ CORRECTION DU BUG : substr(str, 0, 80) — ton code avait substr(str, 80)
        $preview    = substr($this->message->content ?? '', 0, 80);

        return [
            'type'            => 'message',
            'title'           => "💬 Message de {$senderName}",
            'body'            => $preview ?: 'Nouveau message',
            'sender_id'       => $this->message->sender_id,
            'sender_name'     => $senderName,
            // ✅ conversation_id bien présent séparément — le frontend l'utilise pour ouvrir le bon modal
            'conversation_id' => $this->message->conversation_id,
            // ✅ URL simple sans l'ID pour éviter la page blanche
            'url'             => '/messages',
        ];
    }

    public function toFcm($notifiable): FcmMessage
    {
        $senderName = $this->message->sender?->name ?? 'Nouveau message';
        $body       = substr($this->message->content ?? '', 0, 100);

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
            'url'             => '/messages',
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