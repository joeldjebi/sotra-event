<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\Direction;
use App\Models\Mission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mission du convoyeur (CV-01 à CV-03).
 *
 * @mixin Mission
 */
class MissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $point = $this->boardingPoint;
        $outbound = $this->direction === Direction::Outbound;

        return [
            'id' => $this->id,
            'direction' => $this->direction->value,
            'offer' => $this->offer->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'event' => ['id' => $this->event_id, 'name' => $this->event->name, 'venue' => $this->event->venue->name],
            'boarding_point' => ['id' => $point->id, 'commune' => $point->commune, 'place' => $point->place],
            'origin' => $outbound ? $point->place : $this->event->venue->name,
            'destination' => $outbound ? $this->event->venue->name : $point->commune.' · '.$point->place,
            'bus' => $this->bus ? ['id' => $this->bus->id, 'number' => $this->bus->number, 'plate' => $this->bus->plate] : null,
            'driver' => $this->driver ? ['name' => $this->driver->shortName(), 'phone' => $this->driver->phone] : null,
            'planned_departure_at' => $this->planned_departure_at->toIso8601String(),
            'actual_departure_at' => $this->actual_departure_at?->toIso8601String(),
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'capacity' => $this->capacity(),
            'on_board' => $this->onBoardCount(),
            'depot_phone' => $this->bus?->depot?->phone,
        ];
    }
}
