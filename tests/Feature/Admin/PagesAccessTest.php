<?php

use App\Enums\Role;

it('affiche chaque page de gestion au rôle autorisé', function (Role $role, string $url, string $title) {
    $this->actingAs(userWithRole($role))->get($url)->assertOk()->assertSee($title);
})->with([
    [Role::SuperAdmin, '/admin/compagnies', 'Compagnies'],
    [Role::SuperAdmin, '/admin/promoteurs', 'Promoteurs'],
    [Role::SuperAdmin, '/admin/lieux', 'Lieux'],
    [Role::SuperAdmin, '/admin/utilisateurs', 'Utilisateurs'],
    [Role::SuperAdmin, '/admin/journal', 'Journal'],
    [Role::Operator, '/admin/compagnies', 'Compagnies'],
    [Role::Operations, '/compagnie/bus', 'Bus'],
    [Role::Operations, '/compagnie/depots', 'Dépôts'],
    [Role::Operations, '/compagnie/personnel', 'Chauffeurs'],
    [Role::Operations, '/compagnie/equipes-techniques', 'Équipes'],
    [Role::Operations, '/compagnie/terminaux', 'PDA'],
]);

it('refuse les pages hors des permissions du rôle', function (Role $role, string $url) {
    $this->actingAs(userWithRole($role))->get($url)->assertForbidden();
})->with([
    'opérateur sans gestion des utilisateurs' => [Role::Operator, '/admin/utilisateurs'],
    'commercial sur la flotte' => [Role::Commercial, '/compagnie/bus'],
    'qualité sur le personnel' => [Role::Quality, '/compagnie/personnel'],
    'exploitation sur la console' => [Role::Operations, '/admin/compagnies'],
]);

it('neutralise la désactivation de la 2FA proposée par Fortify (NF-03)', function () {
    $user = userWithRole(Role::Commercial);

    $this->actingAs($user)->delete('/user/two-factor-authentication')->assertForbidden();

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});
