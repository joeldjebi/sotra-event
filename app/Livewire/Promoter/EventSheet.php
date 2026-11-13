<?php

namespace App\Livewire\Promoter;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\SelectsEvent;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace promoteur · Fiche événement (PR-07) : informations en lecture, points d'embarquement et horaires.
 */
class EventSheet extends Component
{
    use AuthorizesPermission, SelectsEvent;

    protected function permission(): Permission
    {
        return Permission::EventTracking;
    }

    /**
     * Le promoteur consulte aussi ses événements acceptés, avant l'ouverture de la vente.
     *
     * @return Builder<Event>
     */
    protected function selectableEvents(): Builder
    {
        return Event::query()->where('ends_at', '>', now()->subMonths(3))->orderByDesc('starts_at');
    }

    public function render(): View
    {
        $event = $this->selectedEvent()?->load(['company', 'offers', 'dropZones', 'boardingPoints' => fn ($query) => $query->orderBy('position')]);

        return view('livewire.promoter.event-sheet', [
            'event' => $event,
            'eventOptions' => $this->eventOptions(),
        ]);
    }
}
