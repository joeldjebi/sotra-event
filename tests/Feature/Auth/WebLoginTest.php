<?php

use App\Enums\Role;
use App\Models\User;

it('affiche la page de connexion', function () {
    $this->get(route('login'))->assertOk()->assertSee('Connexion');
});

it('n\'expose pas d\'inscription publique', function () {
    $this->get('/register')->assertNotFound();
});

it('redirige chaque profil vers son espace après connexion', function (Role $role, string $expectedRoute) {
    $user = userWithRole($role, withTwoFactor: false);
    config(['eventtrans.two_factor.enforced' => false]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/espace');

    $this->get(route('space'))->assertRedirect(route($expectedRoute));
})->with([
    [Role::SuperAdmin, 'admin.dashboard'],
    [Role::Operator, 'admin.dashboard'],
    [Role::Commercial, 'company.dashboard'],
    [Role::Quality, 'company.dashboard'],
    [Role::Operations, 'company.dashboard'],
    [Role::TransportLead, 'promoter.dashboard'],
]);

it('refuse un compte désactivé (AD-12)', function () {
    $user = User::factory()->inactive()->withRole(Role::Commercial)->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('déconnecte un compte désactivé pendant sa session', function () {
    $user = userWithRole(Role::Commercial);
    $this->actingAs($user);
    $user->update(['is_active' => false]);

    $this->get(route('company.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('demande le code 2FA aux utilisateurs qui l\'ont activée', function () {
    $user = userWithRole(Role::SuperAdmin);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

it('enregistre la date de dernière connexion', function () {
    config(['eventtrans.two_factor.enforced' => false]);
    $user = userWithRole(Role::Operator, withTwoFactor: false);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});
