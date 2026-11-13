<?php

namespace App\Livewire\Company\Quality;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\SelectsEvent;
use App\Models\Review;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Espace compagnie · Direction qualité · Avis des spectateurs (SP-22, QU-02).
 */
class Reviews extends Component
{
    use AuthorizesPermission, SelectsEvent, WithPagination;

    /** Filtre : avis avec commentaire, ou avis faibles (moyenne < 3). */
    #[Url(as: 'filtre', except: '')]
    public string $filter = '';

    protected function permission(): Permission
    {
        return Permission::QualityView;
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedEventId(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $event = $this->selectedEvent();
        $average = '(punctuality + comfort + courtesy + driving + information) / 5.0';

        return view('livewire.company.quality.reviews', [
            'event' => $event,
            'eventOptions' => $this->eventOptions(),
            'reviews' => Review::query()->with(['mission.bus', 'mission.boardingPoint', 'mission.driver', 'mission.conveyor', 'pass'])
                ->where('event_id', $event->id ?? 0)
                ->when($this->filter === 'commentaires', fn ($query) => $query->whereNotNull('comment'))
                ->when($this->filter === 'faibles', fn ($query) => $query->whereRaw("{$average} < 3"))
                ->latest('id')
                ->paginate(25),
            'criteria' => Review::CRITERIA,
        ]);
    }
}
