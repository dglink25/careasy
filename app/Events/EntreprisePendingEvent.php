<?php
namespace App\Events;

use App\Models\Entreprise;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntreprisePendingEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Entreprise $entreprise;
    public int        $adminId;

    public function __construct(Entreprise $entreprise, int $adminId)
    {
        $this->entreprise = $entreprise;
        $this->adminId    = $adminId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->adminId)];
    }

    public function broadcastAs(): string
    {
        return 'new-entreprise-pending';
    }

    public function broadcastWith(): array
    {
        return [
            'type'             => 'new_entreprise_pending',
            'entreprise_id'    => $this->entreprise->id,
            'entreprise_name'  => $this->entreprise->name,
            'prestataire_name' => $this->entreprise->prestataire?->name,
            'created_at'       => $this->entreprise->created_at->format('d/m/Y H:i'),
            'url'              => '/admin/entreprises/' . $this->entreprise->id,
        ];
    }
}
