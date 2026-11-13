<?php

namespace App\Events;

use App\Models\Mission;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Mise à jour d'une mission (statut, position, passagers) diffusée en temps réel (Reverb) aux tableaux
 * de bord jour J de la compagnie, d'ACCES EVENT et du promoteur.
 */
class MissionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Mission $mission) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('events.'.$this->mission->event_id.'.operations')];
    }

    public function broadcastAs(): string
    {
        return 'mission.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $position = $this->mission->lastPosition();

        return [
            'id' => $this->mission->id,
            'status' => $this->mission->status->value,
            'on_board' => $this->mission->onBoardCount(),
            'latitude' => $position?->location->getLatitude(),
            'longitude' => $position?->location->getLongitude(),
            'recorded_at' => $position?->recorded_at->toIso8601String(),
        ];
    }
}
