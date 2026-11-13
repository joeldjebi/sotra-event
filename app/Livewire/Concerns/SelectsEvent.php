<?php

namespace App\Livewire\Concerns;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Sélecteur d'événement des écrans de suivi (« un sélecteur d'événement filtre tous les écrans », § 8 CDC).
 * Les scopes compagnie et promoteur limitent la liste aux événements de l'utilisateur connecté.
 */
trait SelectsEvent
{
    #[Url(as: 'evenement')]
    public ?int $eventId = null;

    public function mountSelectsEvent(): void
    {
        $this->eventId ??= $this->selectableEvents()->first()?->id;
    }

    /**
     * Événements en vente ou terminés, le plus récent d'abord.
     *
     * @return Builder<Event>
     */
    protected function selectableEvents(): Builder
    {
        return Event::query()->whereIn('status', [EventStatus::OnSale, EventStatus::Finished])->orderByDesc('starts_at');
    }

    protected function selectedEvent(): ?Event
    {
        return $this->eventId ? $this->selectableEvents()->with('venue')->find($this->eventId) : null;
    }

    /**
     * @return array<int, string>
     */
    protected function eventOptions(): array
    {
        return $this->selectableEvents()->pluck('name', 'id')->all();
    }
}
