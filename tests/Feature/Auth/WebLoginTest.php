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

    // Le super administrateur passe par son adresse dédiée.
    $this->post(route($role === Role::SuperAdmin ? 'super-admin.login.store' : 'login.store'), ['email' => $user->email, 'password' => 'password'])
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
    $user = userWithRole(Role::Operator);

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

it('réserve au super administrateur une adresse de connexion distincte de /login', function () {
    $superAdmin = userWithRole(Role::SuperAdmin, ['password' => Hash::make('password')]);
    $operator = userWithRole(Role::Operator, ['password' => Hash::make('password')]);

    $this->get(route('super-admin.login'))->assertOk()->assertSee('Super administrateur')->assertDontSee('Devenir promoteur');

    // Le super administrateur est refusé sur /login, comme un mauvais mot de passe.
    $this->post(route('login.store'), ['email' => $superAdmin->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();

    // Les autres comptes sont refusés sur l'adresse du super administrateur.
    $this->post(route('super-admin.login.store'), ['email' => $operator->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post(route('super-admin.login.store'), ['email' => $superAdmin->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));

    $this->post(route('login.store'), ['email' => $operator->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
});
