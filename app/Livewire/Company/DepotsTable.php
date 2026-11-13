<?php

namespace App\Livewire\Company;

use App\Enums\Permission;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\Depot;
use App\Models\Scopes\CompanyScope;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Exploitation · Dépôts (point d'attache des bus et des bus de réserve).
 */
class DepotsTable extends Component
{
    use ManagesRecords;

    public string $name = '';

    public string $address = '';

    public string $phone = '';

    public string $latitude = '';

    public string $longitude = '';

    protected function permission(): Permission
    {
        return Permission::FleetManage;
    }

    public function create(): void
    {
        $this->reset(['name', 'address', 'phone', 'latitude', 'longitude', 'editingId']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $depotId): void
    {
        $depot = Depot::query()->findOrFail($depotId);
        $this->resetValidation();
        $this->editingId = $depot->id;
        $this->name = $depot->name;
        $this->address = (string) $depot->address;
        $this->phone = (string) $depot->phone;
        $this->latitude = $depot->location ? (string) $depot->location->getLatitude() : '';
        $this->longitude = $depot->location ? (string) $depot->location->getLongitude() : '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('depots', 'name')->where('company_id', CompanyScope::currentCompanyId())->ignore($this->editingId)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ], attributes: ['name' => 'nom']);

        $depot = Depot::query()->updateOrCreate(['id' => $this->editingId], [
            'name' => $this->name,
            'address' => $this->address ?: null,
            'phone' => $this->phone ?: null,
            'location' => $this->latitude !== '' ? Point::makeGeodetic((float) $this->latitude, (float) $this->longitude) : null,
        ]);

        $this->showForm = false;
        $this->notify($this->editingId ? "{$depot->name} mis à jour." : "{$depot->name} ajouté.");
    }

    public function render(): View
    {
        return view('livewire.company.depots-table', [
            'depots' => Depot::query()
                ->withCount('buses')
                ->when($this->search !== '', fn ($query) => $query->whereRaw('lower(name) like ?', [$this->searchTerm()]))
                ->orderBy('name')
                ->paginate(20),
        ]);
    }
}
