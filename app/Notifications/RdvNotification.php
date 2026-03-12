<?php
// app/Notifications/RdvNotification.php

namespace App\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class RdvNotification extends Notification
{
    protected $rdv;
    protected string $action;
    protected ?string $reason;

    public function __construct($rdv, string $action, ?string $reason = null)
    {
        $this->rdv    = $rdv;
        $this->action = $action;
        $this->reason = $reason;
    }

    public function via($notifiable): array
    {
        // Vérifier si Pusher est configuré avant d'activer broadcast
        $pusherConfigured = !empty(config('broadcasting.connections.pusher.key'))
            && config('broadcasting.connections.pusher.key') !== 'your-pusher-key';

        return $pusherConfigured
            ? ['database', 'broadcast']
            : ['database'];
    }

    public function broadcastAs(): string
    {
        return "rdv-{$this->action}";
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->buildData());
    }

    public function toDatabase($notifiable): array
    {
        return $this->buildData();
    }

    private function buildData(): array
    {
        $date   = $this->rdv->date ?? '';
        $time   = $this->rdv->start_time ?? '';
        $client = $this->rdv->client?->name ?? 'Un client';
        $service = $this->rdv->service?->name ?? 'un service';
        $dateFormatted = $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '';

        $titles = [
            'confirmed' => '✅ Rendez-vous confirmé',
            'cancelled' => '❌ Rendez-vous annulé',
            'pending'   => '📅 Nouvelle demande de RDV',
            'completed' => '🎉 Rendez-vous terminé',
        ];
        $types = [
            'confirmed' => 'rdv_confirmed',
            'cancelled' => 'rdv_cancelled',
            'pending'   => 'rdv_pending',
            'completed' => 'rdv_completed',
        ];

        $body = match($this->action) {
            'pending'   => "{$client} demande un RDV pour {$service} le {$dateFormatted} à {$time}",
            'confirmed' => "Votre RDV pour {$service} le {$dateFormatted} à {$time} est confirmé",
            'cancelled' => "Le RDV pour {$service} du {$dateFormatted} a été annulé" . ($this->reason ? " : {$this->reason}" : ''),
            'completed' => "Votre RDV pour {$service} du {$dateFormatted} est terminé",
            default     => "RDV du {$dateFormatted} à {$time}",
        };

        return [
            'type'    => $types[$this->action]  ?? 'rdv_pending',
            'title'   => $titles[$this->action] ?? '📅 Rendez-vous',
            'body'    => $body,
            'rdv_id'  => $this->rdv->id,
            'date'    => $dateFormatted,
            'time'    => $time,
            'client_name' => $client,
            'reason'  => $this->reason,
            'url'     => '/mes-rendez-vous',
        ];
    }
}