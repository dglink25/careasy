<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class CustomResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * Le token de réinitialisation du mot de passe.
     */
    public $token;

    /**
     * L'URL de callback.
     */
    public $callbackUrl;

    /**
     * Créer une nouvelle instance de notification.
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Obtenir les canaux de notification.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Obtenir la représentation mail de la notification.
     */
   public function toMail($notifiable)
{
    $url = $this->resetUrl($notifiable);

    return (new MailMessage)
        ->subject('🔐 Réinitialisation de votre mot de passe CarEasy')
        ->view('emails.reset-password-email', [
            'userName' => $notifiable->name,
            'resetUrl' => $url,
        ]);
}

    /**
     * Obtenir l'URL de réinitialisation.
     */
    protected function resetUrl($notifiable)
    {
        return config('app.frontend_url') . "/password-reset/{$this->token}?email={$notifiable->getEmailForPasswordReset()}";
    }

    /**
     * Obtenir la représentation array de la notification.
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}