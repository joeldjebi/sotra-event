<?php

namespace App\Livewire\Company;

use App\Enums\Permission;
use App\Enums\Role;
use App\Livewire\Concerns\IssuesInitialPasswords;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Équipe SOTRA : l'administrateur SOTRA crée, modifie et désactive les comptes de sa
 * compagnie (administrateurs, direction générale, direction commerciale, direction de l'exploitation), et d'elle seule.
 * Chaque compte reçoit un mot de passe initial, à changer à la première connexion, puis active sa 2FA.
 */
class TeamTable extends Component
{
    use IssuesInitialPasswords, ManagesRecords;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = 'direction_generale';

    public string $initial_password = '';

    protected function permission(): Permission
    {
        return Permission::CompanyTeamManage;
    }

    public function create(): void
    {
        $this->reset(['name', 'email', 'phone', 'role', 'initial_password', 'editingId']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $userId): void
    {
        $user = $this->team()->findOrFail($userId);
        $this->resetValidation();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->role = ($user->primaryRole() ?? Role::GeneralManagement)->value;
        $this->initial_password = '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in($this->assignableRoles())],
            'initial_password' => [Rule::requiredIf($this->editingId === null), 'nullable', 'string', PasswordRule::default()],
        ], attributes: ['name' => 'nom', 'role' => 'rôle', 'initial_password' => 'mot de passe initial']);

        if ($this->editingId === auth()->id() && $this->role !== Role::CompanyAdmin->value) {
            $this->addError('role', 'Vous ne pouvez pas retirer vos propres droits d\'administration.');

            return;
        }

        $user = DB::transaction(function (): User {
            $user = $this->editingId === null
                ? User::query()->create([
                    'name' => $this->name,
                    'email' => Str::lower($this->email),
                    'phone' => $this->phone ?: null,
                    'company_id' => auth()->user()->company_id,
                    'is_active' => true,
                    'password' => Str::password(40),
                ])
                : tap($this->team()->findOrFail($this->editingId))->update([
                    'name' => $this->name,
                    'email' => Str::lower($this->email),
                    'phone' => $this->phone ?: null,
                ]);

            $previousRoles = $user->getRoleNames()->sort()->values()->all();
            $user->syncRoles([$this->role]);

            if ($previousRoles !== [$this->role]) {
                AuditLog::record('roles_changed', $user, ['roles' => $previousRoles], ['roles' => [$this->role]]);
            }

            if (trim($this->initial_password) !== '') {
                $this->issueCredentials($user, trim($this->initial_password));
            }

            return $user;
        });

        $this->showForm = false;
        $this->initial_password = '';
        $this->notify($this->editingId === null ? "Compte créé pour {$user->name}." : "{$user->name} mis à jour.");
    }

    public function toggleActive(int $userId): void
    {
        abort_if($userId === auth()->id(), 403, 'Vous ne pouvez pas désactiver votre propre compte.');

        $user = $this->team()->findOrFail($userId);
        $user->update(['is_active' => ! $user->is_active]);
        $this->notify($user->is_active ? "{$user->name} réactivé." : "{$user->name} désactivé.");
    }

    protected function passwordFields(): array
    {
        return ['initial_password'];
    }

    /**
     * Comptes de la structure de l'utilisateur connecté.
     *
     * @return Builder<User>
     */
    private function team(): Builder
    {
        return User::query()->where('company_id', auth()->user()->company_id);
    }

    /**
     * @return list<string>
     */
    private function assignableRoles(): array
    {
        return array_map(fn (Role $role): string => $role->value, Role::companyTeam());
    }

    public function render(): View
    {
        return view('livewire.company.team-table', [
            'members' => $this->team()
                ->with('roles')
                ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereRaw('lower(name) like ?', [$this->searchTerm()])
                    ->orWhereRaw('lower(email) like ?', [$this->searchTerm()])))
                ->orderBy('name')
                ->paginate(20),
            'roleOptions' => collect(Role::companyTeam())->mapWithKeys(fn (Role $role): array => [$role->value => $role->label()])->all(),
        ]);
    }
}
