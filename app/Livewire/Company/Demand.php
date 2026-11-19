<?php

namespace App\Livewire\Company;

use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Event;
use App\Services\Reporting\CommuneDemand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Espace compagnie · Direction commerciale · Demande par commune : d'où viennent les spectateurs, pour un
 * événement ou pour tous, et si les places prévues au départ de chaque commune suffisent.
 */
class Demand extends Component
{
    use AuthorizesPermission;

    /** Événement choisi ; vide : tous les événements de la compagnie. */
    #[Url(as: 'evenement')]
    public string $eventId = '';

    protected function permission(): Permission
    {
        return Permission::DemandView;
    }

    /**
     * @return Builder<Event>
     */
    private function events(): Builder
    {
        return Event::query()->whereIn('status', [EventStatus::OnSale, EventStatus::Finished, EventStatus::Postponed])->orderByDesc('starts_at');
    }

    public function render(CommuneDemand $demand): View
    {
        $eventIds = $this->events()
            ->when($this->eventId !== '', fn (Builder $query) => $query->whereKey((int) $this->eventId))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return view('livewire.company.demand', [
            'eventOptions' => $this->events()->pluck('name', 'id')->all(),
            'demand' => $demand->build($eventIds),
            'offers' => Offer::cases(),
        ]);
    }
}
