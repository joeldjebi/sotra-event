<?php

use App\Enums\Role;
use App\Livewire\Admin\UsersTable;
use App\Livewire\Security\ChangePassword;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
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

it('crée un chef de projet promoteur avec un mot de passe initial, sans e-mail, affiché une seule fois', function () {
    Notification::fake();
    $promoter = Promoter::factory()->create();

    $component = Livewire::test(UsersTable::class)
        ->call('create')
        ->set('name', 'Paul Promoteur')
        ->set('email', 'paul@promoteur.ci')
        ->set('space', 'promoter')
        ->set('promoter_id', $promoter->id)
        ->set('roles', ['chef_projet'])
        ->set('initial_password', 'court')
        ->call('save')
        ->assertHasErrors('initial_password')
        ->call('generatePassword');

    $password = $component->get('initial_password');
    expect($password)->toMatch('/^[A-Za-z]{10}[2-9]{2}$/');

    $component->call('save')->assertHasNoErrors()
        ->assertSet('issuedCredentials.password', $password)
        ->assertSee($password)
        ->call('dismissCredentials')
        ->assertDontSee($password);

    $user = User::query()->where('email', 'paul@promoteur.ci')->sole();
    expect(Hash::check($password, $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'initial_password_set')->where('auditable_id', $user->id)->exists())->toBeTrue();

    Notification::assertNothingSent();
});

it('impose de changer le mot de passe initial avant d’accéder à l’espace', function () {
    config(['eventtrans.two_factor.enforced' => false]);
    $user = userWithRole(Role::TransportLead, ['promoter_id' => Promoter::factory()->create()->id, 'password' => Hash::make('Provisoire2026'), 'must_change_password' => true]);
    $this->actingAs($user);

    $this->get('/promoteur')->assertRedirect(route('security.password'));
    $this->get(route('security.password'))->assertOk()->assertSee('Choisissez votre mot de passe');

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'mauvais')
        ->set('password', 'Personnel2026abc')
        ->set('password_confirmation', 'Personnel2026abc')
        ->call('save')
        ->assertHasErrors('current_password')
        ->set('current_password', 'Provisoire2026')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('space'));

    expect($user->fresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('Personnel2026abc', $user->fresh()->password))->toBeTrue();
    $this->get('/promoteur')->assertOk();
});

it('redéfinit le mot de passe d’un compte existant', function () {
    $user = userWithRole(Role::Commercial);

    Livewire::test(UsersTable::class)
        ->call('edit', $user->id)
        ->set('initial_password', 'Nouveau2026xyz')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Nouveau2026xyz');

    expect(Hash::check('Nouveau2026xyz', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->must_change_password)->toBeTrue();
});
