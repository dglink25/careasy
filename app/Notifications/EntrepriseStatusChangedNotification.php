<?php
// app/Notifications/EntrepriseStatusChangedNotification.php
//
// ✅ CORRECTIONS :
//   1. ShouldQueue supprimé → envoi immédiat sans worker
//   2. broadcastOn() supprimé → géré par User::receivesBroadcastNotificationsOn()
//   3. broadcastAs() retourne le bon nom d'event côté frontend

namespace App\Notifications;

use App\Models\Entreprise;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EntrepriseStatusChangedNotification extends Notification
{
    // ⚠️  PAS de ShouldQueue — envoi synchrone
    // Si vous avez un worker : ajoutez "implements ShouldQueue" + "use Queueable"

    protected Entreprise $entreprise;
    protected string     $status;    // 'validée' | 'rejetée'
    protected ?string    $adminNote;

    public function __construct(Entreprise $entreprise, string $status, ?string $adminNote = null)
    {
        $this->entreprise = $entreprise;
        $this->status     = $status;
        $this->adminNote  = $adminNote;
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    // ── Nom de l'event Pusher ────────────────────────────────────
    // Le canal est déterminé par User::receivesBroadcastNotificationsOn()
    // L'event arrive côté client sous ce nom exact
    public function broadcastAs(): string
    {
        return $this->status === 'validée'
            ? 'entreprise-approved'
            : 'entreprise-rejected';
    }

    // ── Payload Pusher ────────────────────────────────────────────
    public function toBroadcast($notifiable): BroadcastMessage
    {
        $isApproved = $this->status === 'validée';
        return new BroadcastMessage([
            'type'            => $isApproved ? 'entreprise_approved' : 'entreprise_rejected',
            'title'           => $isApproved
                ? '🎉 Entreprise validée !'
                : '⚠️ Entreprise refusée',
            'body'            => $isApproved
                ? "Votre entreprise \"{$this->entreprise->name}\" a été approuvée ! Période d'essai de 30 jours activée."
                : "Votre demande pour \"{$this->entreprise->name}\" a été refusée."
                  . ($this->adminNote ? " Raison : {$this->adminNote}" : ''),
            'entreprise_id'   => $this->entreprise->id,
            'entreprise_name' => $this->entreprise->name,
            'reason'          => $this->adminNote,
            'url'             => $isApproved ? '/mes-entreprises' : '/entreprises/creer',
        ]);
    }

    // ── Base de données ───────────────────────────────────────────
    public function toDatabase($notifiable): array
    {
        $isApproved = $this->status === 'validée';
        return [
            'type'            => $isApproved ? 'entreprise_approved' : 'entreprise_rejected',
            'title'           => $isApproved ? '🎉 Entreprise validée !' : '⚠️ Entreprise refusée',
            'body'            => $isApproved
                ? "Votre entreprise \"{$this->entreprise->name}\" a été approuvée ! Période d'essai de 30 jours démarre."
                : "Votre demande pour \"{$this->entreprise->name}\" a été refusée."
                  . ($this->adminNote ? " Raison : {$this->adminNote}" : ''),
            'entreprise_id'   => $this->entreprise->id,
            'entreprise_name' => $this->entreprise->name,
            'admin_note'      => $this->adminNote,
            'url'             => $isApproved ? '/mes-entreprises' : '/entreprises/creer',
        ];
    }

    // ── Email HTML ────────────────────────────────────────────────
    public function toMail($notifiable): MailMessage
    {
        $isApproved   = $this->status === 'validée';
        $frontendUrl  = config('app.frontend_url', 'http://localhost:5173');
        $dashboardUrl = $frontendUrl . ($isApproved ? '/mes-entreprises' : '/entreprises/creer');

        return (new MailMessage)
            ->subject($isApproved
                ? '🎉 Votre entreprise a été validée — CarEasy'
                : '⚠️ Demande d\'entreprise refusée — CarEasy')
            ->view('emails.entreprise-validation', [
                'userName'     => $notifiable->name,
                'entreprise'   => $this->entreprise,
                'isApproved'   => $isApproved,
                'adminNote'    => $this->adminNote,
                'dashboardUrl' => $dashboardUrl,
            ]);
    }
}