<?php

namespace App\Livewire\Company;

use App\Enums\AlertStatus;
use App\Enums\DropZoneUsage;
use App\Enums\EventStatus;
use App\Enums\MissionStatus;
use App\Enums\Permission;
use App\Enums\TechTeamStatus;
use App\Exceptions\ApiException;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\BreakdownAlert;
use App\Models\Bus;
use App\Models\Event;
use App\Models\Mission;
use App\Models\TechTeam;
use App\Services\Operations\BreakdownService;
use App\Services\Operations\BusReplacement;
use App\Services\Operations\TripTracker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
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

    /** @var array<int|string, int|string|null> équipe choisie par alerte */
    public array $dispatchTeam = [];

    /** @var array<int|string, int|string|null> bus de réserve choisi par alerte */
    public array $replacementBus = [];

    protected function permission(): Permission
    {
        return Permission::OperationsManage;
    }

    public function mount(): void
    {
        $this->eventId ??= $this->events()->first()?->id;
    }

    /**
     * Envoie une équipe technique disponible sur une panne (prise en charge pour son compte).
     */
    public function sendTeam(int $alertId, BreakdownService $breakdowns): void
    {
        $alert = BreakdownAlert::query()->where('event_id', $this->eventId)->findOrFail($alertId);
        $team = TechTeam::query()->find((int) ($this->dispatchTeam[$alertId] ?? 0));

        if ($team === null) {
            $this->notify('Choisissez une équipe.', 'danger');

            return;
        }

        try {
            $intervention = $breakdowns->take($alert, $team);
            $this->notify("Équipe {$team->code} envoyée · {$intervention->number}.");
        } catch (ApiException $exception) {
            $this->notify($exception->getMessage(), 'danger');
        }
    }

    /**
     * Remplace le bus en panne par un bus de réserve : les Pass suivent, les passagers sont prévenus (RG-20).
     */
    public function replaceBus(int $alertId, BusReplacement $replacement): void
    {
        $alert = BreakdownAlert::query()->with('mission')->where('event_id', $this->eventId)->findOrFail($alertId);
        $bus = Bus::query()->find((int) ($this->replacementBus[$alertId] ?? 0));

        if ($bus === null) {
            $this->notify('Choisissez un bus de réserve.', 'danger');

            return;
        }

        try {
            $replacement->replace($alert->mission, $bus);
            $this->notify("Bus {$bus->number} en remplacement, passagers prévenus.");
        } catch (ValidationException $exception) {
            $this->notify((string) collect($exception->errors())->flatten()->first(), 'danger');
        }
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
            'alerts' => $event === null ? collect() : BreakdownAlert::query()
                ->with(['bus', 'mission.boardingPoint', 'mission.replacedBy.bus', 'reporter', 'techTeam', 'intervention'])
                ->where('event_id', $event->id)
                ->whereIn('status', [AlertStatus::Open, AlertStatus::Taken])
                ->orderByRaw("case urgency when 'urgent' then 0 else 1 end")
                ->get(),
            'teamOptions' => $event === null ? [] : $event->techTeams()->with('techTeam')->get()
                ->filter(fn ($assignment) => $assignment->techTeam->status === TechTeamStatus::Available)
                ->mapWithKeys(fn ($assignment) => [$assignment->tech_team_id => "Équipe {$assignment->techTeam->code} · {$assignment->sector}"])->all(),
            'reserveOptions' => $event === null ? [] : $event->reserveBuses()->with('bus')->get()
                ->mapWithKeys(fn ($reserve) => [$reserve->bus_id => "Bus {$reserve->bus->number} · {$reserve->waiting_place}"])->all(),
        ]);
    }
}
