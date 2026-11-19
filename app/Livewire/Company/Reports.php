<?php

namespace App\Livewire\Company;

use App\Enums\EventStatus;
use App\Enums\PassStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Event;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Espace compagnie · Rapports : pour chaque événement, les documents à télécharger — ventes (Excel),
 * rapport qualité (PDF) et relevé de reversement (PDF), selon les droits de l'utilisateur.
 */
class Reports extends Component
{
    use AuthorizesPermission, WithPagination;

    protected function permission(): Permission
    {
        return Permission::ReportsView;
    }

    public function render(): View
    {
        return view('livewire.company.reports', [
            'events' => Event::query()
                ->with(['venue', 'payout'])
                ->withCount(['passes as sold_count' => fn ($query) => $query->whereIn('status', PassStatus::sold())])
                ->whereIn('status', [EventStatus::OnSale, EventStatus::Finished, EventStatus::Postponed, EventStatus::Cancelled])
                ->orderByDesc('starts_at')
                ->paginate(15),
        ]);
    }
}
