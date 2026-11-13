<?php

namespace App\Livewire\Company;

use App\Enums\BusCategory;
use App\Enums\BusStatus;
use App\Enums\Permission;
use App\Imports\BusesImport;
use App\Livewire\Concerns\HandlesImport;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\Bus;
use App\Models\Depot;
use App\Models\Scopes\CompanyScope;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Espace compagnie · Exploitation · Bus (flotte). Les affectations aux missions arrivent à l'étape 5.
 */
class BusesTable extends Component
{
    use HandlesImport, ManagesRecords;

    #[Url(except: '')]
    public string $statusFilter = '';

    public string $number = '';

    public string $plate = '';

    public string $capacity = '50';

    public string $category = 'standard';

    public string $status = 'en_service';

    public ?int $depot_id = null;

    public string $notes = '';

    protected function permission(): Permission
    {
        return Permission::FleetManage;
    }

    protected function importClass(): string
    {
        return BusesImport::class;
    }

    protected function importAuditAction(): string
    {
        return 'buses_imported';
    }

    protected function templateFileName(): string
    {
        return 'modele-import-bus.xlsx';
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['number', 'plate', 'capacity', 'category', 'status', 'depot_id', 'notes', 'editingId']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $busId): void
    {
        $bus = Bus::query()->findOrFail($busId);
        $this->resetValidation();
        $this->editingId = $bus->id;
        $this->number = $bus->number;
        $this->plate = $bus->plate;
        $this->capacity = (string) $bus->capacity;
        $this->category = $bus->category->value;
        $this->status = $bus->status->value;
        $this->depot_id = $bus->depot_id;
        $this->notes = (string) $bus->notes;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->plate = Str::upper(trim((string) preg_replace('/\s+/', ' ', $this->plate)));
        $companyId = CompanyScope::currentCompanyId();

        $this->validate([
            'number' => ['required', 'string', 'max:20', Rule::unique('buses', 'number')->where('company_id', $companyId)->ignore($this->editingId)],
            'plate' => ['required', 'string', 'max:30', Rule::unique('buses', 'plate')->ignore($this->editingId)],
            'capacity' => ['required', 'integer', 'between:10,200'],
            'category' => ['required', Rule::enum(BusCategory::class)],
            'status' => ['required', Rule::enum(BusStatus::class)],
            'depot_id' => ['nullable', Rule::exists('depots', 'id')->where('company_id', $companyId)],
            'notes' => ['nullable', 'string', 'max:500'],
        ], attributes: ['number' => 'numéro', 'plate' => 'immatriculation', 'capacity' => 'capacité', 'category' => 'gamme', 'depot_id' => 'dépôt']);

        $bus = Bus::query()->updateOrCreate(['id' => $this->editingId], [
            'number' => $this->number,
            'plate' => $this->plate,
            'capacity' => (int) $this->capacity,
            'category' => $this->category,
            'status' => $this->status,
            'depot_id' => $this->depot_id,
            'notes' => $this->notes ?: null,
        ]);

        $this->showForm = false;
        $this->notify($this->editingId ? "Bus {$bus->number} mis à jour." : "Bus {$bus->number} ajouté.");
    }

    public function delete(int $busId): void
    {
        $bus = Bus::query()->findOrFail($busId);
        $bus->delete();
        $this->notify("Bus {$bus->number} supprimé.");
    }

    public function render(): View
    {
        $counts = Bus::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.company.buses-table', [
            'buses' => Bus::query()
                ->with('depot')
                ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
                ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereRaw('lower(number) like ?', [$this->searchTerm()])
                    ->orWhereRaw('lower(plate) like ?', [$this->searchTerm()])))
                ->orderByRaw('length(number), number')
                ->paginate(20),
            'counts' => $counts,
            'total' => (int) $counts->sum(),
            'categories' => BusCategory::options(),
            'statuses' => BusStatus::options(),
            'depots' => Depot::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
