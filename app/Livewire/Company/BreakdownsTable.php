<?php

namespace App\Livewire\Company;

use App\Enums\AlertStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\BreakdownAlert;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Espace compagnie · Exploitation · Pannes & interventions : alertes, équipes, étapes, résultats (P4).
 */
class BreakdownsTable extends Component
{
    use AuthorizesPermission, WithPagination;

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    public ?int $viewingId = null;

    public bool $showDetails = false;

    protected function permission(): Permission
    {
        return Permission::BreakdownsManage;
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function show(int $alertId): void
    {
        $this->viewingId = BreakdownAlert::query()->findOrFail($alertId)->id;
        $this->showDetails = true;
    }

    public function render(): View
    {
        $relations = ['bus', 'mission.boardingPoint', 'mission.replacedBy.bus', 'reporter', 'techTeam', 'intervention.replacementBus'];

        return view('livewire.company.breakdowns-table', [
            'alerts' => BreakdownAlert::query()
                ->with(array_merge($relations, ['mission.event']))
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->latest()
                ->paginate(20),
            'statuses' => AlertStatus::options(),
            'viewing' => $this->viewingId ? BreakdownAlert::query()->with($relations)->find($this->viewingId) : null,
            'openCount' => BreakdownAlert::query()->where('status', '!=', AlertStatus::Closed)->count(),
        ]);
    }
}
