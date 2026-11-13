<?php

use App\Enums\Role;
use App\Livewire\Admin\UsersTable;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = userWithRole(Role::SuperAdmin);
    $this->actingAs($this->admin);
});

it('crée un utilisateur de compagnie et lui envoie une invitation (AD-12)', function () {
    Notification::fake();
    $company = Company::factory()->create();

    Livewire::test(UsersTable::class)
        ->call('create')
        ->set('name', 'Clarisse Commerciale')
        ->set('email', 'Clarisse@SOTRA.ci')
        ->set('space', 'company')
        ->set('company_id', $company->id)
        ->set('roles', ['commercial', 'qualite'])
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'clarisse@sotra.ci')->sole();

    expect($user->company_id)->toBe($company->id)
        ->and($user->getRoleNames()->sort()->values()->all())->toBe(['commercial', 'qualite']);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('refuse un rôle qui n\'appartient pas à l\'espace choisi', function () {
    Livewire::test(UsersTable::class)
        ->call('create')
        ->set('name', 'X')
        ->set('email', 'x@test.ci')
        ->set('space', 'promoter')
        ->set('promoter_id', null)
        ->set('roles', ['super_admin'])
        ->call('save')
        ->assertHasErrors(['roles.0', 'promoter_id']);
});

it('journalise le changement de rôles', function () {
    $user = userWithRole(Role::Commercial);

    Livewire::test(UsersTable::class)
        ->call('edit', $user->id)
        ->set('roles', ['exploitation'])
        ->call('save')
        ->assertHasNoErrors();

    expect(AuditLog::query()->where('action', 'roles_changed')->where('auditable_id', $user->id)->sole()->new_values)
        ->toBe(['roles' => ['exploitation']]);
});

it('réinitialise la 2FA d\'un utilisateur', function () {
    $user = userWithRole(Role::Commercial);

    Livewire::test(UsersTable::class)->call('resetTwoFactor', $user->id);

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'two_factor_reset')->exists())->toBeTrue();
});

it('empêche un administrateur de se désactiver ou de perdre ses droits', function () {
    Livewire::test(UsersTable::class)->call('toggleActive', $this->admin->id)->assertForbidden();

    Livewire::test(UsersTable::class)
        ->call('edit', $this->admin->id)
        ->set('roles', ['operateur'])
        ->call('save')
        ->assertHasErrors('roles');

    expect($this->admin->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('filtre les utilisateurs par espace', function () {
    userWithRole(Role::Commercial, ['name' => 'Compagnie Personne']);
    userWithRole(Role::TransportLead, ['name' => 'Promoteur Personne']);

    Livewire::test(UsersTable::class)
        ->set('spaceFilter', 'promoter')
        ->assertSee('Promoteur Personne')
        ->assertDontSee('Compagnie Personne');
});
