<?php

namespace App\Livewire\Company;

use App\Enums\BusStatus;
use App\Enums\Direction;
use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Bus;
use App\Models\Event;
use App\Models\EventOffer;
use App\Models\Mission;
use App\Services\Operations\MissionPlanner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Exploitation · Plan de transport (RG-08) :
 * - capacité : avant l'ouverture des ventes, bus × places par offre ;
 * - missions : après les ventes, bus nécessaires par point, offre et sens, générés à la demande.
 */
class TransportPlan extends Component
{
    use AuthorizesPermission;

    /** @var array<int|string, array{bus_count: string, seats_per_bus: string}> indexé par id d'EventOffer */
    public array $capacity = [];

    /** @var array<int|string, list<string>> avertissements de la dernière génération, par événement */
    public array $planWarnings = [];

    protected function permission(): Permission
    {
        return Permission::OperationsManage;
    }

    public function mount(): void
    {
        foreach ($this->editableEvents() as $event) {
            foreach ($event->offers as $offer) {
                $this->capacity[$offer->id] = [
                    'bus_count' => (string) $offer->bus_count,
                    'seats_per_bus' => (string) ($offer->seats_per_bus ?? 50),
                ];
            }
        }
    }

    public function save(int $eventId): void
    {
        $event = Event::query()
            ->whereIn('status', [EventStatus::Proposed, EventStatus::Negotiating, EventStatus::Accepted])
            ->with('offers')
            ->findOrFail($eventId);

        $rules = [];

        foreach ($event->offers as $offer) {
            $rules["capacity.{$offer->id}.bus_count"] = ['required', 'integer', 'between:1,1000'];
            $rules["capacity.{$offer->id}.seats_per_bus"] = ['required', 'integer', 'between:10,200'];
        }

        $this->validate($rules, [
            'capacity.*.bus_count.required' => 'Nombre de bus requis.',
            'capacity.*.bus_count.between' => 'Entre 1 et 1 000 bus.',
            'capacity.*.seats_per_bus.required' => 'Places requises.',
            'capacity.*.seats_per_bus.between' => 'Entre 10 et 200 places.',
        ]);

        if ($event->offers->isEmpty()) {
            throw ValidationException::withMessages(['capacity' => 'Aucune offre active sur cet événement.']);
        }

        foreach ($event->offers as $offer) {
            $offer->update([
                'bus_count' => (int) $this->capacity[$offer->id]['bus_count'],
                'seats_per_bus' => (int) $this->capacity[$offer->id]['seats_per_bus'],
            ]);
        }

        $this->notify("Capacité de « {$event->name} » enregistrée.");
    }

    /**
     * Génère ou actualise les missions d'un événement à partir des ventes (RG-08).
     */
    public function generate(int $eventId, MissionPlanner $planner): void
    {
        $event = Event::query()->whereIn('status', [EventStatus::Accepted, EventStatus::OnSale])->findOrFail($eventId);

        try {
            $result = $planner->plan($event);
        } catch (ValidationException $exception) {
            $this->notify((string) collect($exception->errors())->flatten()->first(), 'danger');

            return;
        }

        $this->planWarnings[$eventId] = $result['warnings'];
        $this->notify("Plan actualisé : {$result['created']} mission(s) ajoutée(s), {$result['removed']} retirée(s), {$result['assigned']} Pass affecté(s).");
    }

    /**
     * Demande et missions par point et par offre, pour le tableau du plan.
     *
     * @return list<array{point: string, offer: string, outbound: int, outbound_needed: int, outbound_missions: int, return: int, return_needed: int, return_missions: int}>
     */
    private function planRows(Event $event, MissionPlanner $planner): array
    {
        $seats = $event->offers->mapWithKeys(fn (EventOffer $offer) => [$offer->offer->value => $offer->seats_per_bus])->all();
        $missions = Mission::query()->where('event_id', $event->id)
            ->selectRaw('boarding_point_id, offer, direction, count(*) as total')
            ->groupBy('boarding_point_id', 'offer', 'direction')
            ->get();
        $rows = [];

        foreach ($planner->demand($event) as $demand) {
            $key = $demand['point']->id.'|'.$demand['offer']->value;
            $rows[$key] ??= [
                'point' => $demand['point']->commune.' · '.$demand['point']->place,
                'offer' => $demand['offer']->label(),
                'outbound' => 0, 'outbound_needed' => 0, 'outbound_missions' => 0,
                'return' => 0, 'return_needed' => 0, 'return_missions' => 0,
            ];
            $prefix = $demand['direction'] === Direction::Outbound ? 'outbound' : 'return';
            $perBus = $seats[$demand['offer']->value] ?? null;
            $rows[$key][$prefix] = $demand['passengers'];
            $rows[$key][$prefix.'_needed'] = $perBus ? (int) ceil($demand['passengers'] / $perBus) : 0;
            $rows[$key][$prefix.'_missions'] = (int) $missions->first(fn (Mission $mission) => $mission->boarding_point_id === $demand['point']->id
                && $mission->offer === $demand['offer']
                && $mission->direction === $demand['direction'])?->getAttribute('total');
        }

        return array_values($rows);
    }

    /**
     * @return Collection<int, Event>
     */
    private function editableEvents()
    {
        return Event::query()
            ->with(['venue', 'offers'])
            ->whereIn('status', [EventStatus::Proposed, EventStatus::Negotiating, EventStatus::Accepted])
            ->orderBy('starts_at')
            ->get();
    }

    public function render(MissionPlanner $planner): View
    {
        $fleet = Bus::query()
            ->whereIn('status', [BusStatus::InService, BusStatus::Reserve])
            ->selectRaw('category, count(*) as total, coalesce(sum(capacity), 0) as seats')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $plannable = Event::query()->with(['venue', 'offers'])
            ->whereIn('status', [EventStatus::Accepted, EventStatus::OnSale])
            ->where('ends_at', '>', now()->subDay())
            ->orderBy('starts_at')
            ->get();

        return view('livewire.company.transport-plan', [
            'events' => $this->editableEvents(),
            'plannable' => $plannable,
            'planRows' => $plannable->mapWithKeys(fn (Event $event) => [$event->id => $this->planRows($event, $planner)])->all(),
            'fleet' => $fleet,
            'capacityOf' => fn (EventOffer $offer): ?int => isset($this->capacity[$offer->id])
                && ctype_digit($this->capacity[$offer->id]['bus_count']) && ctype_digit($this->capacity[$offer->id]['seats_per_bus'])
                    ? (int) $this->capacity[$offer->id]['bus_count'] * (int) $this->capacity[$offer->id]['seats_per_bus']
                    : null,
        ]);
    }
}
