<?php

namespace App\Livewire\Promoter;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\SelectsEvent;
use App\Services\Reporting\PromoterReport;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace promoteur · Bilan (PR-09) : transportés aller et retour, ponctualité, incidents, note ; export PDF.
 */
class Report extends Component
{
    use AuthorizesPermission, SelectsEvent;

    protected function permission(): Permission
    {
        return Permission::EventTracking;
    }

    public function render(PromoterReport $reports): View
    {
        $event = $this->selectedEvent();

        return view('livewire.promoter.report', [
            'event' => $event,
            'eventOptions' => $this->eventOptions(),
            'report' => $event ? $reports->build($event) : null,
        ]);
    }
}
