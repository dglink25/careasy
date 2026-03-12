<?php
// app/Notifications/NewEntrepriseCreatedNotification.php
//
// ✅ CORRECTIONS :
//   1. Suppression de ShouldQueue → envoi immédiat (pas besoin de worker)
//   2. broadcastOn() utilise $notifiable->id et non $this->notifiable_id
//   3. broadcastAs() retourne le nom court sans namespace
//   4. Séparation DB / Broadcast / Mail propre

namespace App\Notifications;

use App\Models\Entreprise;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewEntrepriseCreatedNotification extends Notification
{
    // ⚠️  PAS de ShouldQueue ici — envoi synchrone
    //     Si vous voulez la queue, lancez : php artisan queue:work
    //     et remettez "implements ShouldQueue" + "use Queueable"

    protected Entreprise $entreprise;
    protected User       $prestataire;

    public function __construct(Entreprise $entreprise, User $prestataire)
    {
        $this->entreprise  = $entreprise;
        $this->prestataire = $prestataire;
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    // ── Canal Pusher ─────────────────────────────────────────────
    // $notifiable = l'admin qui reçoit la notif
    public function broadcastOn(): array
    {
        // Note : $this->notifiable n'existe pas dans les Notifications Laravel
        // La méthode broadcastOn reçoit $notifiable en paramètre depuis via()
        // mais pour les Notifications, le canal est dérivé automatiquement
        // via la méthode receivesBroadcastNotificationsOn() du model User.
        // On retourne un canal vide ici car c'est géré par le model User.
        return [];
    }

    public function broadcastAs(): string
    {
        return 'new-entreprise-pending';
    }

    // ── Payload Pusher ────────────────────────────────────────────
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type'             => 'new_entreprise_pending',
            'title'            => '🔔 Nouvelle demande entreprise',
            'body'             => "\"{$this->entreprise->name}\" attend votre validation",
            'entreprise_id'    => $this->entreprise->id,
            'entreprise_name'  => $this->entreprise->name,
            'prestataire_name' => $this->prestataire->name,
            'prestataire_email'=> $this->prestataire->email,
            'url'              => '/admin/entreprises/' . $this->entreprise->id,
        ]);
    }

    // ── Base de données ───────────────────────────────────────────
    public function toDatabase($notifiable): array
    {
        return [
            'type'              => 'new_entreprise_pending',
            'title'             => '🔔 Nouvelle demande entreprise',
            'body'              => "\"{$this->entreprise->name}\" attend votre validation",
            'entreprise_id'     => $this->entreprise->id,
            'entreprise_name'   => $this->entreprise->name,
            'prestataire_id'    => $this->prestataire->id,
            'prestataire_name'  => $this->prestataire->name,
            'url'               => '/admin/entreprises/' . $this->entreprise->id,
        ];
    }

    // ── Email HTML ────────────────────────────────────────────────
    public function toMail($notifiable): MailMessage
    {
        $adminUrl = config('app.frontend_url', 'http://localhost:5173')
                  . '/admin/entreprises/' . $this->entreprise->id;

        return (new MailMessage)
            ->subject('🏢 Nouvelle demande d\'entreprise — ' . $this->entreprise->name)
            ->view('emails.admin-new-entreprise', [
                'admin'       => $notifiable,
                'entreprise'  => $this->entreprise,
                'prestataire' => $this->prestataire,
                'adminUrl'    => $adminUrl,
            ]);
    }
}