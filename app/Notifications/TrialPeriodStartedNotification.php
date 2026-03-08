<?php
// app/Notifications/TrialPeriodStartedNotification.php

namespace App\Notifications;

use App\Models\Entreprise;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialPeriodStartedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $entreprise;

    public function __construct(Entreprise $entreprise) {
        $this->entreprise = $entreprise;
    }

    public function via($notifiable) {
        return ['mail', 'database'];
    }

    public function toMail($notifiable){
        $joursRestants = $this->entreprise->trial_days_remaining;
        
        return (new MailMessage)
            ->subject('Votre période d\'essai gratuit est activée !')
            ->greeting('Bonjour ' . $notifiable->name . ' !')
            ->line('Félicitations ! Votre entreprise "' . $this->entreprise->name . '" a été validée et votre période d\'essai gratuit de 30 jours est activée.')
            ->line('**Détails de votre essai gratuit :**')
            ->line('- Durée : 30 jours')
            ->line('- Date de fin : ' . $this->entreprise->trial_ends_at->format('d/m/Y'))
            ->line('- Services autorisés : ' . $this->entreprise->max_services_allowed . ' services maximum')
            ->line('- Employés autorisés : ' . $this->entreprise->max_employees_allowed . ' employé maximum')
            ->line('- Envoie Notificaion SMS : ' . ($this->entreprise->has_api_access ? 'Oui' : 'Non'))
            ->line('')
            ->line('**Ce que vous pouvez faire maintenant :**')
            ->line('- Créer vos services (maximum ' . $this->entreprise->max_services_allowed . ')')
            ->line('- Configurer votre boutique')
            ->line('- Ajouter vos informations de contact')
            ->line('')
            ->action('Accéder à mon entreprise', url('/prestataire/entreprises/' . $this->entreprise->id))
            ->line('Profitez pleinement de cette période d\'essai pour découvrir toutes les fonctionnalités de notre plateforme !')
            ->line('À bientôt !');
    }

    public function toArray($notifiable){
        return [
            'type' => 'trial_started',
            'entreprise_id' => $this->entreprise->id,
            'entreprise_name' => $this->entreprise->name,
            'message' => 'Période d\'essai gratuit activée pour votre entreprise',
            'trial_ends_at' => $this->entreprise->trial_ends_at->format('d/m/Y'),
            'days_remaining' => $this->entreprise->trial_days_remaining,
            'max_services' => $this->entreprise->max_services_allowed
        ];
    }
}