<?php

use App\Enums\Role;
use App\Livewire\Admin\Dashboard;
use Livewire\Livewire;

it('affiche le tableau de bord de la console aux profils ACCES EVENT', function (Role $role) {
    eventOnSale();

    $this->actingAs(userWithRole($role))
        ->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class)
        ->assertSee('Chiffre d\'affaires encaissé', false)
        ->assertSee('Remplissage des événements en vente')
        ->assertSee('Aucune panne en cours');
})->with([Role::SuperAdmin, Role::Operator]);

it('refuse le tableau de bord de la console aux profils compagnie', function () {
    $this->actingAs(userWithRole(Role::Commercial))->get('/admin')->assertForbidden();
});

it('change de période et de compagnie sans recharger la page', function () {
    $event = eventOnSale();

    Livewire::actingAs(userWithRole(Role::Operator))
        ->test(Dashboard::class)
        ->assertSet('period', 30)
        ->call('setPeriod', 7)
        ->assertSet('period', 7)
        ->call('setPeriod', 12)
        ->assertSet('period', 30)
        ->set('company', (string) $event->company_id)
        ->assertOk()
        ->assertSee($event->name);
});

it('refuse les requêtes Livewire d\'un profil compagnie', function () {
    Livewire::actingAs(userWithRole(Role::Commercial))->test(Dashboard::class)->assertForbidden();
});
