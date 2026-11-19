<?php

namespace App\Livewire\Promoter;

use App\Enums\EventStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\Event;
use App\Services\Reporting\PromoterEventInsights;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Espace promoteur · Mes événements : tous les événements du promoteur à partir de leur acceptation,
 * passés, en cours et à venir, avec Pass vendus, capacité et remplissage. Le scope promoteur cloisonne la liste.
 */
class EventsIndex extends Component
{
    use ManagesRecords;

    /** a-venir · termines · tous */
    #[Url(as: 'periode', except: 'a-venir')]
    public string $period = 'a-venir';

    protected function permission(): Permission
    {
        return Permission::EventTracking;
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $events = Event::query()
            ->with(['venue', 'company', 'offers'])
            ->whereIn('status', PromoterEventInsights::visibleStatuses())
            ->when($this->period === 'a-venir', fn ($query) => $query->where('ends_at', '>=', now())->where('status', '!=', EventStatus::Cancelled))
            ->when($this->period === 'termines', fn ($query) => $query->where(fn ($query) => $query->where('ends_at', '<', now())->orWhere('status', EventStatus::Cancelled)))
            ->when($this->search !== '', fn ($query) => $query->whereRaw('lower(name) like ?', [$this->searchTerm()]))
            ->when($this->period === 'a-venir', fn ($query) => $query->orderBy('starts_at'), fn ($query) => $query->orderByDesc('starts_at'))
            ->paginate(15);

        return view('livewire.promoter.events-index', [
            'events' => $events,
            'sold' => PromoterEventInsights::soldByEvent($events->pluck('id')->all()),
        ]);
    }
}
