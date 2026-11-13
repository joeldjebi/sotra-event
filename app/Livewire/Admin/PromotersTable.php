<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\Promoter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Console ACCES EVENT · Promoteurs (AD-09). Les chefs de projet transport sont des utilisateurs
 * rattachés au promoteur (menu Utilisateurs & rôles).
 */
class PromotersTable extends Component
{
    use ManagesRecords;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public bool $is_active = true;

    protected function permission(): Permission
    {
        return Permission::PromotersManage;
    }

    public function create(): void
    {
        $this->reset(['name', 'email', 'phone', 'address', 'is_active', 'editingId']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $promoterId): void
    {
        $promoter = Promoter::query()->findOrFail($promoterId);
        $this->resetValidation();
        $this->editingId = $promoter->id;
        $this->name = $promoter->name;
        $this->email = (string) $promoter->email;
        $this->phone = (string) $promoter->phone;
        $this->address = (string) $promoter->address;
        $this->is_active = $promoter->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('promoters', 'name')->ignore($this->editingId)],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ], attributes: ['name' => 'nom']);

        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);

        $promoter = Promoter::query()->updateOrCreate(['id' => $this->editingId], $data);

        $this->showForm = false;
        $this->notify($this->editingId ? "{$promoter->name} mis à jour." : "{$promoter->name} ajouté.");
    }

    public function render(): View
    {
        return view('livewire.admin.promoters-table', [
            'promoters' => Promoter::query()
                ->with(['users' => fn ($query) => $query->role(Role::TransportLead->value)->orderBy('name')])
                ->when($this->search !== '', fn ($query) => $query->whereRaw('lower(name) like ?', [$this->searchTerm()]))
                ->orderBy('name')
                ->paginate(15),
        ]);
    }
}
