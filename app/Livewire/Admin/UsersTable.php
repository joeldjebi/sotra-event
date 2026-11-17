<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\Space;
use App\Livewire\Concerns\IssuesInitialPasswords;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Console ACCES EVENT · Utilisateurs & rôles (AD-12).
 *
 * Un compte créé reçoit soit un lien par e-mail pour choisir son mot de passe, soit un mot de passe initial donné
 * par l'administrateur (affiché une seule fois, à changer à la première connexion). Il active sa 2FA à la première connexion.
 */
class UsersTable extends Component
{
    use IssuesInitialPasswords, ManagesRecords;

    #[Url(except: '')]
    public string $spaceFilter = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $space = 'company';

    /** @var list<string> */
    public array $roles = [];

    public ?int $company_id = null;

    public ?int $promoter_id = null;

    public bool $is_active = true;

    /** Mot de passe initial donné par l'administrateur (facultatif) ; sinon, lien par e-mail. */
    public string $initial_password = '';

    protected function permission(): Permission
    {
        return Permission::UsersManage;
    }

    public function updatedSpaceFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSpace(): void
    {
        $this->roles = [];
    }

    public function create(): void
    {
        $this->reset(['name', 'email', 'phone', 'space', 'roles', 'company_id', 'promoter_id', 'is_active', 'editingId', 'initial_password']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->resetValidation();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->space = ($user->space() ?? Space::Company)->value;
        $this->roles = array_map(fn (Role $role): string => $role->value, $user->businessRoles());
        $this->company_id = $user->company_id;
        $this->promoter_id = $user->promoter_id;
        $this->is_active = $user->is_active;
        $this->initial_password = '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $space = Space::from($this->space);
        $allowedRoles = array_map(fn (Role $role): string => $role->value, Role::forSpace($space));

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'space' => ['required', Rule::enum(Space::class)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in($allowedRoles)],
            'company_id' => [Rule::requiredIf($space === Space::Company), 'nullable', 'exists:companies,id'],
            'promoter_id' => [Rule::requiredIf($space === Space::Promoter), 'nullable', 'exists:promoters,id'],
            'is_active' => ['boolean'],
            'initial_password' => ['nullable', 'string', PasswordRule::default()],
        ], attributes: ['name' => 'nom', 'roles' => 'rôles', 'company_id' => 'compagnie', 'promoter_id' => 'promoteur', 'initial_password' => 'mot de passe initial']);

        $initialPassword = trim($this->initial_password);

        $isSelf = $this->editingId === auth()->id();

        if ($isSelf && (! $this->is_active || ! in_array(Role::SuperAdmin->value, $this->roles, true))) {
            $this->addError('roles', 'Vous ne pouvez pas retirer vos propres droits d\'administration ni désactiver votre compte.');

            return;
        }

        $user = DB::transaction(function () use ($space, $initialPassword): User {
            $user = User::query()->updateOrCreate(['id' => $this->editingId], [
                'name' => $this->name,
                'email' => Str::lower($this->email),
                'phone' => $this->phone ?: null,
                'company_id' => $space === Space::Company ? $this->company_id : null,
                'promoter_id' => $space === Space::Promoter ? $this->promoter_id : null,
                'is_active' => $this->is_active,
            ] + ($this->editingId === null ? ['password' => Str::password(40)] : []));

            if ($initialPassword !== '') {
                $this->issueCredentials($user, $initialPassword);
            }

            $previousRoles = $user->getRoleNames()->sort()->values()->all();
            $user->syncRoles($this->roles);
            $newRoles = collect($this->roles)->sort()->values()->all();

            if ($previousRoles !== $newRoles) {
                AuditLog::record('roles_changed', $user, ['roles' => $previousRoles], ['roles' => $newRoles]);
            }

            return $user;
        });

        if ($initialPassword === '' && $this->editingId === null) {
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        $this->showForm = false;
        $this->initial_password = '';
        $this->notify(match (true) {
            $initialPassword !== '' => "Mot de passe initial défini pour {$user->name}.",
            $this->editingId !== null => "{$user->name} mis à jour.",
            default => "Invitation envoyée à {$user->email}.",
        });
    }

    protected function passwordFields(): array
    {
        return ['initial_password'];
    }

    public function toggleActive(int $userId): void
    {
        abort_if($userId === auth()->id(), 403, 'Vous ne pouvez pas désactiver votre propre compte.');

        $user = User::query()->findOrFail($userId);
        $user->update(['is_active' => ! $user->is_active]);
        $this->notify($user->is_active ? "{$user->name} réactivé." : "{$user->name} désactivé.");
    }

    public function resetTwoFactor(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        AuditLog::record('two_factor_reset', $user);
        $this->notify("Double authentification réinitialisée pour {$user->name}.");
    }

    public function sendInvitation(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        Password::broker()->sendResetLink(['email' => $user->email]);
        $this->notify("Lien de connexion renvoyé à {$user->email}.");
    }

    public function render(): View
    {
        $space = Space::tryFrom($this->space) ?? Space::Company;

        return view('livewire.admin.users-table', [
            'users' => User::query()
                ->with(['roles', 'company', 'promoter'])
                ->when($this->spaceFilter !== '', function ($query): void {
                    $roles = array_map(fn (Role $role): string => $role->value, Role::forSpace(Space::from($this->spaceFilter)));
                    $query->role($roles);
                })
                ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereRaw('lower(name) like ?', [$this->searchTerm()])
                    ->orWhereRaw('lower(email) like ?', [$this->searchTerm()])))
                ->orderBy('name')
                ->paginate(20),
            'spaces' => collect(Space::cases())->mapWithKeys(fn (Space $item): array => [$item->value => $item->label()])->all(),
            'availableRoles' => Role::forSpace($space),
            'companies' => Company::query()->orderBy('name')->pluck('name', 'id')->all(),
            'promoters' => Promoter::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
