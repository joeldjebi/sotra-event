<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\Company;
use App\Models\Device;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Console · Direction technique ACCES TRANS · Terminaux (PDA et smartphones des convoyeurs), toutes compagnies :
 * état, version de l'application, dernière synchronisation. Consultation seule : chaque compagnie gère les siens.
 */
class DevicesOverview extends Component
{
    use ManagesRecords;

    /** Au-delà, un terminal actif est signalé « à vérifier » (il ne synchronise plus). */
    public const STALE_HOURS = 24;

    #[Url(as: 'compagnie', except: '')]
    public string $company = '';

    protected function permission(): Permission
    {
        return Permission::OperationsSupervise;
    }

    public function updatedCompany(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.admin.devices-overview', [
            'devices' => Device::query()
                ->with(['company', 'staff'])
                ->when($this->company !== '', fn ($query) => $query->where('company_id', (int) $this->company))
                ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereRaw('lower(label) like ?', [$this->searchTerm()])
                    ->orWhereRaw('lower(serial_number) like ?', [$this->searchTerm()])))
                ->orderByRaw('last_sync_at asc nulls first')
                ->paginate(25),
            'companies' => Company::query()->orderBy('name')->pluck('name', 'id')->all(),
            'staleBefore' => now()->subHours(self::STALE_HOURS),
        ]);
    }
}
