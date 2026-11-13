<?php

namespace App\Livewire\Admin;

use App\Enums\PayoutMethod;
use App\Enums\Permission;
use App\Livewire\Concerns\ManagesRecords;
use App\Livewire\Forms\CompanyForm;
use App\Models\Company;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Console ACCES EVENT · Compagnies (AD-08).
 */
class CompaniesTable extends Component
{
    use ManagesRecords;

    public CompanyForm $form;

    protected function permission(): Permission
    {
        return Permission::CompaniesManage;
    }

    public function create(): void
    {
        $this->form->reset();
        $this->form->resetValidation();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $companyId): void
    {
        $this->form->resetValidation();
        $this->form->fillFrom(Company::query()->findOrFail($companyId));
        $this->editingId = $companyId;
        $this->showForm = true;
    }

    public function save(): void
    {
        $company = $this->form->save();
        $this->showForm = false;
        $this->notify($this->editingId ? "{$company->name} mise à jour." : "{$company->name} ajoutée.");
    }

    public function toggleActive(int $companyId): void
    {
        $company = Company::query()->findOrFail($companyId);
        $company->update(['is_active' => ! $company->is_active]);
        $this->notify($company->is_active ? "{$company->name} réactivée." : "{$company->name} désactivée.");
    }

    public function render(): View
    {
        return view('livewire.admin.companies-table', [
            'companies' => Company::query()
                ->withCount(['users', 'buses', 'staff'])
                ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereRaw('lower(name) like ?', [$this->searchTerm()])
                    ->orWhereRaw('lower(coalesce(legal_name, \'\')) like ?', [$this->searchTerm()])))
                ->orderBy('name')
                ->paginate(15),
            'payoutMethods' => PayoutMethod::options(),
        ]);
    }
}
