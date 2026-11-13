<?php

namespace App\Livewire\Company\Quality;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\SelectsEvent;
use App\Services\Reporting\QualityMetrics;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Direction qualité · Bilans du pilote (QU-07) : comparaison des événements entre eux.
 */
class PilotReview extends Component
{
    use AuthorizesPermission, SelectsEvent;

    protected function permission(): Permission
    {
        return Permission::QualityView;
    }

    public function render(): View
    {
        return view('livewire.company.quality.pilot-review', [
            'rows' => QualityMetrics::compare($this->selectableEvents()->with('venue')->limit(12)->get()),
        ]);
    }
}
