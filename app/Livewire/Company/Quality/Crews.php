<?php

namespace App\Livewire\Company\Quality;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\SelectsEvent;
use App\Services\Reporting\QualityMetrics;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Direction qualité · Équipages (QU-04) : notes du chauffeur et du convoyeur, écart au départ.
 */
class Crews extends Component
{
    use AuthorizesPermission, SelectsEvent;

    protected function permission(): Permission
    {
        return Permission::QualityView;
    }

    public function render(): View
    {
        $event = $this->selectedEvent();

        return view('livewire.company.quality.crews', [
            'event' => $event,
            'eventOptions' => $this->eventOptions(),
            'crews' => $event ? (new QualityMetrics($event))->crews() : [],
        ]);
    }
}
