<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Livewire\Concerns\IssuesInitialPasswords;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Console ACCES EVENT · Promoteurs (AD-09). Les chefs de projet transport sont des utilisateurs rattachés au
 * promoteur : le premier compte peut être créé ici, avec un mot de passe initial défini par l'administrateur
 * (les suivants dans Utilisateurs & rôles).
 */
class PromotersTable extends Component
{
    use IssuesInitialPasswords, ManagesRecords;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public bool $is_active = true;

    /** Créer en même temps le compte de connexion du chef de projet transport. */
    public bool $createAccount = true;

    public string $account_name = '';

    public string $account_email = '';

    public string $account_password = '';

    protected function permission(): Permission
    {
        return Permission::PromotersManage;
    }

    public function create(): void
    {
        $this->reset(['name', 'email', 'phone', 'address', 'is_active', 'editingId', 'account_name', 'account_email', 'account_password']);
        $this->createAccount = true;
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
        $this->reset(['account_name', 'account_email', 'account_password']);
        // En modification, le compte n'est proposé que si le promoteur n'en a encore aucun.
        $this->createAccount = false;
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

        if ($this->createAccount) {
            $this->validate([
                'account_name' => ['required', 'string', 'max:120'],
                'account_email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
                'account_password' => ['required', 'string', PasswordRule::default()],
            ], attributes: ['account_name' => 'nom du chef de projet', 'account_email' => 'e-mail de connexion', 'account_password' => 'mot de passe']);
        }

        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);

        $promoter = DB::transaction(function () use ($data): Promoter {
            $promoter = Promoter::query()->updateOrCreate(['id' => $this->editingId], $data);

            if ($this->createAccount) {
                $user = User::query()->create([
                    'name' => trim($this->account_name),
                    'email' => Str::lower(trim($this->account_email)),
                    'promoter_id' => $promoter->id,
                    'is_active' => true,
                    'password' => Str::password(40),
                ]);
                $user->assignRole(Role::TransportLead->value);
                $this->issueCredentials($user, $this->account_password);
            }

            return $promoter;
        });

        $this->showForm = false;
        $this->notify(match (true) {
            $this->createAccount && $this->editingId === null => "{$promoter->name} ajouté, avec son compte de connexion.",
            $this->createAccount => "Compte de connexion créé pour {$promoter->name}.",
            $this->editingId !== null => "{$promoter->name} mis à jour.",
            default => "{$promoter->name} ajouté.",
        });
        $this->reset(['account_password']);
    }

    /**
     * Reprend le nom et l'e-mail du promoteur pour le compte, si l'administrateur ne les a pas déjà saisis.
     */
    public function updatedEmail(string $value): void
    {
        if ($this->createAccount && $this->account_email === '') {
            $this->account_email = $value;
        }
    }

    protected function passwordFields(): array
    {
        return ['account_password'];
    }

    public function render(): View
    {
        return view('livewire.admin.promoters-table', [
            'editingHasAccount' => $this->editingId !== null
                && User::query()->where('promoter_id', $this->editingId)->exists(),
            'promoters' => Promoter::query()
                ->with(['users' => fn ($query) => $query->role(Role::TransportLead->value)->orderBy('name')])
                ->when($this->search !== '', fn ($query) => $query->whereRaw('lower(name) like ?', [$this->searchTerm()]))
                ->orderBy('name')
                ->paginate(15),
        ]);
    }
}
