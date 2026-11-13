<?php

use App\Enums\Role;

it('renvoie un visiteur vers la connexion', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with(['/admin', '/compagnie', '/promoteur', '/espace']);

it('cloisonne les espaces selon le rôle', function (Role $role, string $allowed, array $forbidden) {
    $this->actingAs(userWithRole($role));

    $this->get($allowed)->assertOk();

    foreach ($forbidden as $url) {
        $this->get($url)->assertForbidden();
    }
})->with([
    'super admin' => [Role::SuperAdmin, '/admin', ['/compagnie', '/promoteur']],
    'commercial' => [Role::Commercial, '/compagnie', ['/admin', '/promoteur']],
    'qualité' => [Role::Quality, '/compagnie', ['/admin', '/promoteur']],
    'exploitation' => [Role::Operations, '/compagnie', ['/admin', '/promoteur']],
    'chef de projet' => [Role::TransportLead, '/promoteur', ['/admin', '/compagnie']],
]);

it('affiche dans le menu uniquement les rubriques du rôle', function () {
    $this->actingAs(userWithRole(Role::Commercial))
        ->get('/compagnie')
        ->assertOk()
        ->assertSee('Ventes')
        ->assertSee('Événements proposés')
        ->assertDontSee('Exploitation en direct')
        ->assertDontSee('Qualité de service');
});

it('refuse l\'espace compagnie à un utilisateur sans compagnie', function () {
    $user = userWithRole(Role::Commercial);
    $user->update(['company_id' => null]);

    $this->actingAs($user)->get('/compagnie')->assertForbidden();
});
