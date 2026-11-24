<?php

namespace App\Livewire\Company;

use App\Actions\Users\SetInitialPassword;
use App\Enums\Permission;
use App\Enums\StaffRole;
use App\Imports\StaffImport;
use App\Livewire\Concerns\HandlesImport;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\AuditLog;
use App\Models\Scopes\CompanyScope;
use App\Models\Staff;
use App\Models\TechTeam;
use App\Rules\ValidPhoneNumber;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Espace compagnie · Exploitation · Chauffeurs & convoyeurs (et techniciens).
 * Convoyeurs et techniciens se connectent aux apps mobiles avec leur téléphone et un mot de passe : l'initial est
 * généré ici, affiché une seule fois, et devra être changé à la première connexion.
 */
class StaffTable extends Component
{
    use HandlesImport, ManagesRecords;

    #[Url(except: '')]
    public string $roleFilter = '';

    public string $role = 'convoyeur';

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public string $employee_number = '';

    public ?int $tech_team_id = null;

    public bool $is_active = true;

    /** @var array{name: string, phone: string, password: string}|null identifiants à transmettre, affichés une seule fois */
    public ?array $issuedCredentials = null;

    protected function permission(): Permission
    {
        return Permission::StaffView;
    }

    protected function managePermission(): Permission
    {
        return Permission::StaffManage;
    }

    protected function importClass(): string
    {
        return StaffImport::class;
    }

    protected function importAuditAction(): string
    {
        return 'staff_imported';
    }

    protected function templateFileName(): string
    {
        return 'modele-import-personnel.xlsx';
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->reset(['role', 'first_name', 'last_name', 'phone', 'employee_number', 'tech_team_id', 'is_active', 'editingId']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $staffId): void
    {
        $this->authorizeManage();
        $staff = Staff::query()->findOrFail($staffId);
        $this->resetValidation();
        $this->editingId = $staff->id;
        $this->role = $staff->role->value;
        $this->first_name = $staff->first_name;
        $this->last_name = $staff->last_name;
        $this->phone = $staff->phone;
        $this->employee_number = (string) $staff->employee_number;
        $this->tech_team_id = $staff->tech_team_id;
        $this->is_active = $staff->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $companyId = CompanyScope::currentCompanyId();

        $this->validate([
            'role' => ['required', Rule::enum(StaffRole::class)],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', new ValidPhoneNumber],
            'employee_number' => ['nullable', 'string', 'max:40', Rule::unique('staff', 'employee_number')->where('company_id', $companyId)->ignore($this->editingId)],
            'tech_team_id' => ['nullable', Rule::exists('tech_teams', 'id')->where('company_id', $companyId)],
            'is_active' => ['boolean'],
        ], attributes: ['role' => 'fonction', 'first_name' => 'prénom', 'last_name' => 'nom', 'employee_number' => 'matricule', 'tech_team_id' => 'équipe']);

        $phone = PhoneNumber::parse($this->phone)->e164;

        $duplicate = Staff::query()->withoutGlobalScopes()
            ->where('phone', $phone)
            ->when($this->editingId !== null, fn ($query) => $query->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicate) {
            $this->addError('phone', 'Ce numéro est déjà attribué à un membre du personnel.');

            return;
        }

        $staff = Staff::query()->updateOrCreate(['id' => $this->editingId], [
            'role' => $this->role,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $phone,
            'employee_number' => $this->employee_number ?: null,
            'tech_team_id' => $this->role === StaffRole::Technician->value ? $this->tech_team_id : null,
            'is_active' => $this->is_active,
        ]);

        if (! $staff->is_active) {
            $staff->tokens()->delete();
        }

        if ($staff->canSignIn() && $staff->password === null) {
            $this->issuePassword($staff);
        }

        $this->showForm = false;
        $this->notify($this->editingId ? "{$staff->fullName()} mis à jour." : "{$staff->fullName()} ajouté.");
    }

    /**
     * Nouveau mot de passe initial (oubli, import Excel, compte créé avant les mots de passe) : les appareils
     * connectés sont déconnectés.
     */
    public function resetPassword(int $staffId): void
    {
        $this->authorizeManage();
        $staff = Staff::query()->findOrFail($staffId);
        abort_unless($staff->canSignIn(), 400);

        $staff->tokens()->delete();
        $this->issuePassword($staff);
        $this->notify("Nouveau mot de passe pour {$staff->fullName()}.");
    }

    public function dismissCredentials(): void
    {
        $this->issuedCredentials = null;
    }

    private function issuePassword(Staff $staff): void
    {
        $password = SetInitialPassword::generate();
        $staff->forceFill(['password' => $password, 'must_change_password' => true])->save();
        AuditLog::record('staff_password_issued', $staff);
        $this->issuedCredentials = ['name' => $staff->fullName(), 'phone' => $staff->phone, 'password' => $password];
    }

    public function render(): View
    {
        $counts = Staff::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return view('livewire.company.staff-table', [
            'members' => Staff::query()
                ->with('techTeam')
                ->when($this->roleFilter !== '', fn ($query) => $query->where('role', $this->roleFilter))
                ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereRaw("lower(first_name || ' ' || last_name) like ?", [$this->searchTerm()])
                    ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $this->search).'%')
                    ->orWhereRaw('lower(coalesce(employee_number, \'\')) like ?', [$this->searchTerm()])))
                ->orderBy('last_name')
                ->paginate(20),
            'counts' => $counts,
            'roles' => StaffRole::options(),
            'teams' => TechTeam::query()->orderBy('code')->pluck('code', 'id')->all(),
        ]);
    }
}
