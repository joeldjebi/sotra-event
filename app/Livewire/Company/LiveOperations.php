<?php

namespace App\Livewire\Company;

use App\Enums\DropZoneUsage;
use App\Enums\EventStatus;
use App\Enums\MissionStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Event;
use App\Models\Mission;
use App\Services\Operations\TripTracker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Espace compagnie · Exploitation en direct (jour J) : compteurs, carte des bus, affectations et positions.
 * Rafraîchi toutes les 10 s (NF-06 : 30 s au plus) ; les événements temps réel (Reverb) complètent ce rafraîchissement.
 */
class LiveOperations extends Component
{
    use AuthorizesPermission;

    #[Url(as: 'evenement')]
    public ?int $eventId = null;

    #[Url(as: 'sens')]
    public string $direction = 'aller';

    protected function permission(): Permission
    {
        return Permission::OperationsManage;
    }

    public function mount(): void
    {
        $this->eventId ??= $this->events()->first()?->id;
    }

    /**
     * @return Builder<Event>
     */
    private function events()
    {
        return Event::query()
            ->whereIn('status', [EventStatus::OnSale, EventStatus::Accepted])
            ->where('ends_at', '>', now()->subHours(12))
            ->orderBy('starts_at');
    }

    public function render(TripTracker $tracker): View
    {
        $event = $this->eventId ? $this->events()->with(['venue', 'dropZones'])->find($this->eventId) : null;
        $missions = $event === null ? collect() : Mission::query()
            ->with(['boardingPoint', 'bus', 'driver', 'conveyor', 'event.venue', 'event.dropZones'])
            ->where('event_id', $event->id)
            ->where('direction', $this->direction)
            ->orderBy('planned_departure_at')
            ->orderBy('boarding_point_id')
            ->get();

        $rows = $missions->map(function (Mission $mission) use ($tracker) {
            $position = $mission->lastPosition();

            return [
                'mission' => $mission,
                'on_board' => $mission->onBoardCount(),
                'position' => $position,
                'eta' => $tracker->etaMinutes($mission),
            ];
        });

        $markers = $rows->filter(fn (array $row) => $row['position'] !== null)->map(fn (array $row) => [
            'id' => $row['mission']->id,
            'lat' => $row['position']->location->getLatitude(),
            'lng' => $row['position']->location->getLongitude(),
            'label' => $row['mission']->bus ? 'Bus '.$row['mission']->bus->number : $row['mission']->boardingPoint->commune,
            'tone' => match ($row['mission']->status) {
                MissionStatus::OnRoute, MissionStatus::Arrived => 'success',
                MissionStatus::Boarding => 'warning',
                MissionStatus::Incident => 'danger',
                default => 'neutral',
            },
        ])->values();

        $venue = $event?->venue;

        return view('livewire.company.live-operations', [
            'event' => $event,
            'eventOptions' => $this->events()->pluck('name', 'id')->all(),
            'rows' => $rows,
            'counts' => [
                'en_route' => $missions->where('status', MissionStatus::OnRoute)->count(),
                'embarquement' => $missions->where('status', MissionStatus::Boarding)->count(),
                'panne' => $missions->where('status', MissionStatus::Incident)->count(),
                'arrivee' => $missions->where('status', MissionStatus::Arrived)->count(),
                'reserve' => $event?->reserveBuses()->count() ?? 0,
            ],
            'map' => [
                'center' => $venue ? [$venue->location->getLatitude(), $venue->location->getLongitude()] : null,
                'zoom' => 11,
                'fit' => true,
                'markers' => $markers->all(),
                'zones' => $event === null ? [] : $event->dropZones->map(fn ($zone) => [
                    'lat' => $zone->location->getLatitude(),
                    'lng' => $zone->location->getLongitude(),
                    'radius' => $zone->radius_m,
                    'label' => $zone->name.' · '.$zone->usage->label(),
                ])->values()->all(),
            ],
            'dropOff' => $event?->dropZones->firstWhere('usage', DropZoneUsage::DropOff),
        ]);
    }
}
