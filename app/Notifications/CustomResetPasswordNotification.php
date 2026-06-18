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
    public function toMail($notifiable) {
        $url = $this->resetUrl($notifiable);

        // Déterminer les adresses depuis la config, avec fallback
        $fromAddress = config('mail.from.address') ?? ('no-reply@' . parse_url(config('app.url') ?: 'localhost', PHP_URL_HOST));
        $fromName = config('mail.from.name') ?? config('app.name', 'CarEasy');
        $replyTo = config('mail.reply_to.address') ?? $fromAddress;

        $mail = (new MailMessage)
            ->from($fromAddress, $fromName)
            ->replyTo($replyTo)
            ->subject(trim('Réinitialisation de votre mot de passe CarEasy'))
            ->view('emails.reset-password-email', [
                'userName' => $notifiable->name,
                'resetUrl' => $url,
            ]);

        $callback = function ($message) {
            try {
                $headers = $message->getHeaders();
                $headers->addTextHeader('X-Mailer', 'CarEasy Mailer');
                $headers->addTextHeader('Precedence', 'list');
                $headers->addTextHeader('X-Priority', '3');

                $host = parse_url(config('app.url') ?: '', PHP_URL_HOST);
                if ($host) {
                    $headers->addTextHeader('List-Unsubscribe', '<mailto:support@' . $host . '?subject=unsubscribe>');
                }
            } 
            catch (\Exception $e) {
                // ignore header errors
            }
        };

        if (method_exists($mail, 'withSymfonyMessage')) {
            $mail->withSymfonyMessage($callback);
        } elseif (method_exists($mail, 'withSwiftMessage')) {
            $mail->withSwiftMessage($callback);
        }

        return $mail;
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