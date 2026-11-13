<?php

namespace App\Livewire\Company;

use App\Enums\BusStatus;
use App\Enums\EventStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Bus;
use App\Models\Event;
use App\Models\EventOffer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Exploitation · Plan de transport, volet capacité (RG-08) :
 * avant l'ouverture des ventes, la compagnie fixe par offre le nombre de bus et de places par bus.
 * La génération des missions et l'affectation des bus arrivent à l'étape 5.
 */
class TransportPlan extends Component
{
    use AuthorizesPermission;

    /** @var array<int|string, array{bus_count: string, seats_per_bus: string}> indexé par id d'EventOffer */
    public array $capacity = [];

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

    public function render(): View
    {
        $fleet = Bus::query()
            ->whereIn('status', [BusStatus::InService, BusStatus::Reserve])
            ->selectRaw('category, count(*) as total, coalesce(sum(capacity), 0) as seats')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        return view('livewire.company.transport-plan', [
            'events' => $this->editableEvents(),
            'onSale' => Event::query()->with(['venue', 'offers'])->where('status', EventStatus::OnSale)->orderBy('starts_at')->get(),
            'fleet' => $fleet,
            'capacityOf' => fn (EventOffer $offer): ?int => isset($this->capacity[$offer->id])
                && ctype_digit($this->capacity[$offer->id]['bus_count']) && ctype_digit($this->capacity[$offer->id]['seats_per_bus'])
                    ? (int) $this->capacity[$offer->id]['bus_count'] * (int) $this->capacity[$offer->id]['seats_per_bus']
                    : null,
        ]);
    }
}
