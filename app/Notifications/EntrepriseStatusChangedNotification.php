<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EntrepriseStatusChangedNotification extends Notification
{
    use Queueable;

    private $entreprise;
    private $status;
    private $adminNote;

    public function __construct($entreprise, $status, $adminNote = null)
    {
        $this->entreprise = $entreprise;
        $this->status = $status;
        $this->adminNote = $adminNote;
    }

    /**
     * Canaux de notification - SANS NEXMO pour éviter l'erreur
     */
    public function via($notifiable)
    {
        // Seulement database et mail (nexmo retiré temporairement)
        return ['database', 'mail'];
    }

    /**
     * Email notification
     */
    public function toMail($notifiable)
    {
        $message = (new MailMessage)
            ->subject("Votre demande d'entreprise a été {$this->status}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("La demande d'enregistrement de l'entreprise \"{$this->entreprise->name}\" a été {$this->status}.");

        if ($this->adminNote) {
            $message->line("Remarque de l'administrateur : {$this->adminNote}");
        }

        $message->line('Merci d\'utiliser CarEasy.');

        return $message;
    }

    /**
     * Database notification
     */
    public function toDatabase($notifiable)
    {
        return [
            'entreprise_id' => $this->entreprise->id,
            'entreprise_name' => $this->entreprise->name,
            'status' => $this->status,
            'admin_note' => $this->adminNote,
        ];
    }
}