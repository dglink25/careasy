<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EntrepriseStatusChangedNotification extends Notification{
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

    public function via($notifiable)
    {
        // database + mail + custom sms channel
        return ['database', 'mail', 'nexmo']; // si nexmo est configuré
    }

    public function toMail($notifiable)
    {
        $message = (new MailMessage)
            ->subject("Votre demande d'entreprise a été {$this->status}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("La demande d'enregistrement de l'entreprise \"{$this->entreprise->name}\" a été {$this->status}.");

        if ($this->adminNote) {
            $message->line("Remarque de l'administrateur : {$this->adminNote}");
        }

        $message->action('Voir l’entreprise', url('/account/entreprises/'.$this->entreprise->id));
        $message->line('Merci d’utiliser Careasy.');

        return $message;
    }

    public function toDatabase($notifiable)
    {
        return [
            'entreprise_id' => $this->entreprise->id,
            'entreprise_name' => $this->entreprise->name,
            'status' => $this->status,
            'admin_note' => $this->adminNote,
        ];
    }

    public function toNexmo($notifiable)
    {
        return (new NexmoMessage)
                    ->content("Careasy: votre entreprise '{$this->entreprise->name}' a été {$this->status}.");
    }
}
