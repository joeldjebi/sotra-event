<?php

namespace App\Livewire\Promoter;

use App\Enums\DropZoneUsage;
use App\Enums\EventStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\EditsDropZones;
use App\Livewire\Concerns\SelectsEvent;
use App\Models\DropZone;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace promoteur · Zones de dépose et de reprise (PR-05) : lieu (ex. « Parking P3 · porte Nord ») et usage
 * (dépose aller, reprise retour avec heure, stand d'accueil). Modifiables jusqu'à la fin de l'événement.
 */
class DropZones extends Component
{
    use AuthorizesPermission, EditsDropZones, SelectsEvent;

    protected function permission(): Permission
    {
        return Permission::EventTracking;
    }

    /**
     * @return Builder<Event>
     */
    protected function selectableEvents(): Builder
    {
        return Event::query()->whereIn('status', [EventStatus::Accepted, EventStatus::OnSale])->where('ends_at', '>', now())->orderBy('starts_at');
    }

    protected function dropZoneEvent(): Event
    {
        return $this->selectedEvent() ?? abort(404);
    }

    public function render(): View
    {
        $event = $this->selectedEvent()?->load('dropZones');
        $venue = $event?->venue;

        return view('livewire.promoter.drop-zones', [
            'event' => $event,
            'eventOptions' => $this->eventOptions(),
            'zones' => $event?->dropZones->sortBy(fn (DropZone $zone) => $zone->usage->value)->values() ?? collect(),
            'usages' => DropZoneUsage::options(),
            'map' => $event === null ? null : [
                'center' => $venue ? [$venue->location->getLatitude(), $venue->location->getLongitude()] : null,
                'zoom' => 16,
                'fit' => $event->dropZones->isNotEmpty(),
                'markers' => $venue ? [['id' => 0, 'lat' => $venue->location->getLatitude(), 'lng' => $venue->location->getLongitude(), 'label' => $venue->name, 'tone' => 'neutral']] : [],
                'zones' => $event->dropZones->map(fn (DropZone $zone) => [
                    'lat' => $zone->location->getLatitude(),
                    'lng' => $zone->location->getLongitude(),
                    'radius' => $zone->radius_m,
                    'label' => $zone->name.' · '.$zone->usage->label(),
                ])->values()->all(),
            ],
        ]);
    }
}
