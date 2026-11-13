<?php

namespace App\Livewire\Company\Quality;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\SelectsEvent;
use App\Models\Complaint;
use App\Services\Reporting\QualityMetrics;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Direction qualité · Qualité de service, bilan J+1 (QU-01, QU-02, QU-05).
 */
class Overview extends Component
{
    use AuthorizesPermission, SelectsEvent;

    protected function permission(): Permission
    {
        return Permission::QualityView;
    }

    public function render(): View
    {
        $event = $this->selectedEvent();
        $metrics = $event ? new QualityMetrics($event) : null;

        return view('livewire.company.quality.overview', [
            'event' => $event,
            'eventOptions' => $this->eventOptions(),
            'summary' => $metrics?->summary(),
            'criteria' => $metrics?->criteria() ?? [],
            'crews' => $metrics ? array_slice($metrics->crews(), 0, 6) : [],
            'complaints' => $event === null ? collect() : Complaint::query()->with(['mission.bus', 'mission.boardingPoint', 'pass'])
                ->where('event_id', $event->id)->latest('id')->limit(5)->get(),
        ]);
    }
}
