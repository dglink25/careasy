<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InactivityReminderNotification extends Notification implements ShouldQueue{
    use Queueable;

    public function __construct(
        private readonly int $inactiveDays,
        private readonly int $reminderNumber = 1
    ) {}

    public function via($notifiable): array {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage {
        $firstName   = explode(' ', $notifiable->name)[0];
        $frontendUrl = config('app.frontend_url', 'https://careasy26.vercel.app');
        $loginUrl    = $frontendUrl . '/login';

        return (new MailMessage)
            ->subject("{$firstName}, vous nous manquez ! Revenez sur CarEasy")
            ->view('emails.inactivity-reminder', [
                'userName'      => $notifiable->name,
                'firstName'     => $firstName,
                'inactiveDays'  => $this->inactiveDays,
                'reminderNumber'=> $this->reminderNumber,
                'loginUrl'      => $loginUrl,
                'frontendUrl'   => $frontendUrl,
            ]);
    }

    public function toArray($notifiable): array {
        return [
            'type'           => 'inactivity_reminder',
            'title'          => ' Vous nous manquez !',
            'body'           => "Vous n'avez pas utilisé CarEasy depuis {$this->inactiveDays} jours.",
            'inactive_days'  => $this->inactiveDays,
            'reminder_number'=> $this->reminderNumber,
            'url'            => '/',
        ];
    }
}