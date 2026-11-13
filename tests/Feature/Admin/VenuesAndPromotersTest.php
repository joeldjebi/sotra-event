<?php

use App\Enums\Role;
use App\Livewire\Admin\PromotersTable;
use App\Livewire\Admin\VenuesTable;
use App\Models\Promoter;
use App\Models\Venue;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(userWithRole(Role::Operator)));

it('enregistre un lieu avec sa position GPS', function () {
    Livewire::test(VenuesTable::class)
        ->call('create')
        ->set('name', 'Stade Olympique d\'Ebimpé')
        ->set('latitude', '5.4925')
        ->set('longitude', '-4.0791')
        ->call('save')
        ->assertHasNoErrors();

    $venue = Venue::query()->sole();

    expect($venue->location->getLatitude())->toEqualWithDelta(5.4925, 0.00001)
        ->and($venue->location->getLongitude())->toEqualWithDelta(-4.0791, 0.00001);
});

it('refuse des coordonnées hors limites', function () {
    Livewire::test(VenuesTable::class)
        ->call('create')
        ->set('name', 'Lieu')
        ->set('latitude', '95')
        ->set('longitude', '-200')
        ->call('save')
        ->assertHasErrors(['latitude', 'longitude']);
});

it('crée et modifie un promoteur', function () {
    Livewire::test(PromotersTable::class)
        ->call('create')
        ->set('name', 'Promoteur Démo')
        ->set('email', 'contact@promoteur.ci')
        ->call('save')
        ->assertHasNoErrors();

    $promoter = Promoter::query()->sole();

    Livewire::test(PromotersTable::class)
        ->call('edit', $promoter->id)
        ->set('phone', '+2250700000099')
        ->call('save');

    expect($promoter->fresh()->phone)->toBe('+2250700000099');
});
