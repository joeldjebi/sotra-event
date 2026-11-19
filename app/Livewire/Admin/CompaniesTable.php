<?php

namespace App\Livewire\Admin;

use App\Enums\PayoutMethod;
use App\Enums\Permission;
use App\Enums\Role;
use App\Livewire\Concerns\IssuesInitialPasswords;
use App\Livewire\Concerns\ManagesRecords;
use App\Livewire\Forms\CompanyForm;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Console ACCES EVENT · Compagnies (AD-08). Le super admin y crée l'administrateur de la compagnie
 * (« Administrateur SOTRA »), qui crée ensuite lui-même les comptes de ses directions.
 */
class CompaniesTable extends Component
{
    use IssuesInitialPasswords, ManagesRecords;

    public CompanyForm $form;

    public bool $showAdminForm = false;

    #[Locked]
    public ?int $adminCompanyId = null;

    public string $admin_name = '';

    public string $admin_email = '';

    public string $admin_phone = '';

    public string $admin_password = '';

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

    public function createAdmin(int $companyId): void
    {
        $this->adminCompanyId = Company::query()->findOrFail($companyId)->id;
        $this->reset(['admin_name', 'admin_email', 'admin_phone', 'admin_password']);
        $this->resetValidation();
        $this->showAdminForm = true;
    }

    public function saveAdmin(): void
    {
        $company = Company::query()->findOrFail($this->adminCompanyId);

        $this->validate([
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
            'admin_phone' => ['nullable', 'string', 'max:30'],
            'admin_password' => ['required', 'string', PasswordRule::default()],
        ], attributes: ['admin_name' => 'nom', 'admin_email' => 'e-mail de connexion', 'admin_phone' => 'téléphone', 'admin_password' => 'mot de passe initial']);

        $user = DB::transaction(function () use ($company): User {
            $user = User::query()->create([
                'name' => trim($this->admin_name),
                'email' => Str::lower(trim($this->admin_email)),
                'phone' => trim($this->admin_phone) ?: null,
                'company_id' => $company->id,
                'is_active' => true,
                'password' => Str::password(40),
            ]);
            $user->syncRoles([Role::CompanyAdmin->value]);
            AuditLog::record('roles_changed', $user, ['roles' => []], ['roles' => [Role::CompanyAdmin->value]]);
            $this->issueCredentials($user, $this->admin_password);

            return $user;
        });

        $this->showAdminForm = false;
        $this->admin_password = '';
        $this->notify("Administrateur {$company->name} créé : {$user->name}.");
    }

    protected function passwordFields(): array
    {
        return ['admin_password'];
    }

    public function render(): View
    {
        return view('livewire.admin.companies-table', [
            'companies' => Company::query()
                ->withCount(['users', 'buses', 'staff'])
                ->with(['users' => fn ($query) => $query->role(Role::CompanyAdmin->value)->orderBy('name')])
                ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereRaw('lower(name) like ?', [$this->searchTerm()])
                    ->orWhereRaw('lower(coalesce(legal_name, \'\')) like ?', [$this->searchTerm()])))
                ->orderBy('name')
                ->paginate(15),
            'payoutMethods' => PayoutMethod::options(),
        ]);
    }
}
