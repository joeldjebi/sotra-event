<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Enums\StaffRole;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Staff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Console · Utilisateurs des applications mobiles : spectateurs (app et site) et personnel qui se connecte
 * (convoyeurs, techniciens) de toutes les compagnies. Consultation seule : chaque compagnie gère son personnel,
 * le centre d'appel traite les demandes des spectateurs.
 */
class MobileUsersTable extends Component
{
    use ManagesRecords;

    #[Url(as: 'onglet', except: 'spectateurs')]
    public string $tab = 'spectateurs';

    #[Url(as: 'compagnie', except: '')]
    public string $company = '';

    protected function permission(): Permission
    {
        return Permission::MobileUsersView;
    }

    public function updatedTab(): void
    {
        $this->tab = in_array($this->tab, ['spectateurs', 'personnel'], true) ? $this->tab : 'spectateurs';
        $this->reset(['search', 'company']);
        $this->resetPage();
    }

    public function updatedCompany(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.admin.mobile-users-table', [
            'customers' => $this->tab === 'spectateurs' ? $this->customers() : null,
            'staff' => $this->tab === 'personnel' ? $this->staff() : null,
            'customerCount' => Customer::query()->whereNull('anonymized_at')->count(),
            'staffCount' => Staff::query()->withoutGlobalScopes()->whereIn('role', $this->signInRoles())->count(),
            'companies' => Company::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    private function customers(): LengthAwarePaginator
    {
        $digits = (string) preg_replace('/\D/', '', $this->search);

        return Customer::query()
            ->whereNull('anonymized_at')
            ->withCount(['passes', 'orders'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw("lower(coalesce(first_name, '') || ' ' || coalesce(last_name, '')) like ?", [$this->searchTerm()])
                ->when($digits !== '', fn ($query) => $query->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%'))))
            ->latest('id')
            ->paginate(25);
    }

    /**
     * @return LengthAwarePaginator<int, Staff>
     */
    private function staff(): LengthAwarePaginator
    {
        $digits = (string) preg_replace('/\D/', '', $this->search);

        return Staff::query()->withoutGlobalScopes()
            ->with('company')
            ->whereIn('role', $this->signInRoles())
            ->when($this->company !== '', fn ($query) => $query->where('company_id', (int) $this->company))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw("lower(first_name || ' ' || last_name) like ?", [$this->searchTerm()])
                ->when($digits !== '', fn ($query) => $query->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%'))))
            ->orderBy('last_name')
            ->paginate(25);
    }

    /**
     * @return list<string>
     */
    private function signInRoles(): array
    {
        return array_values(array_map(fn (StaffRole $role): string => $role->value, array_filter(StaffRole::cases(), fn (StaffRole $role): bool => $role->canSignIn())));
    }
}
