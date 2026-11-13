<?php

namespace App\Livewire\Promoter;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\ComposesPromoterMessages;
use App\Livewire\Concerns\SelectsEvent;
use App\Services\Reporting\ArrivalTracking;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace promoteur · Suivi de l'événement (PR-01 à PR-04) : en-tête, indicateurs en temps réel,
 * flux d'arrivée prévu / réel par tranche de 15 min, retards, message aux spectateurs. Rafraîchi toutes les 15 s.
 */
class Tracking extends Component
{
    use AuthorizesPermission, ComposesPromoterMessages, SelectsEvent;

    protected function permission(): Permission
    {
        return Permission::EventTracking;
    }

    public function render(): View
    {
        $event = $this->selectedEvent()?->load(['company', 'dropZones']);
        $tracking = $event ? app()->make(ArrivalTracking::class, ['event' => $event]) : null;

        return view('livewire.promoter.tracking', [
            'event' => $event,
            'eventOptions' => $this->eventOptions(),
            'summary' => $tracking?->summary(),
            'flow' => $tracking?->flow(),
            'composer' => $event ? $this->composerData($event) : null,
        ]);
    }
}
