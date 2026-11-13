<?php

namespace App\Livewire\Company\Quality;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\SelectsEvent;
use App\Services\Reporting\QualityMetrics;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Espace compagnie · Direction qualité · Ponctualité (QU-06) : prévu, réel et écart par départ, et pannes.
 */
class Punctuality extends Component
{
    use AuthorizesPermission, SelectsEvent;

    #[Url(as: 'sens')]
    public string $direction = 'aller';

    protected function permission(): Permission
    {
        return Permission::QualityView;
    }

    public function render(): View
    {
        $event = $this->selectedEvent();
        $metrics = $event ? new QualityMetrics($event) : null;
        $rows = $metrics?->punctuality() ?? ['aller' => [], 'retour' => []];

        return view('livewire.company.quality.punctuality', [
            'event' => $event,
            'eventOptions' => $this->eventOptions(),
            'summary' => $metrics?->summary(),
            'rows' => $rows[$this->direction === 'retour' ? 'retour' : 'aller'],
            'breakdowns' => $metrics?->breakdowns() ?? collect(),
        ]);
    }
}
