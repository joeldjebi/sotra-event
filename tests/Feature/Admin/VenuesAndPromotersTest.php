<?php

use App\Enums\Role;
use App\Livewire\Admin\PromotersTable;
use App\Livewire\Admin\VenuesTable;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Hash;
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
        ->set('createAccount', false)
        ->call('save')
        ->assertHasNoErrors();

    $promoter = Promoter::query()->sole();

    Livewire::test(PromotersTable::class)
        ->call('edit', $promoter->id)
        ->set('phone', '+2250700000099')
        ->call('save');

    expect($promoter->fresh()->phone)->toBe('+2250700000099');
});

it('crée un promoteur avec le compte de connexion de son chef de projet et un mot de passe défini', function () {
    $component = Livewire::test(PromotersTable::class)
        ->call('create')
        ->set('name', 'Live Nation CI')
        ->set('email', 'contact@livenation.ci')
        ->assertSet('account_email', 'contact@livenation.ci')
        ->set('account_name', 'Awa Koné')
        ->set('account_password', 'court')
        ->call('save')
        ->assertHasErrors('account_password')
        ->set('account_password', 'Promoteur2026ok')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Promoteur2026ok')
        ->assertSee('Awa Koné');

    $promoter = Promoter::query()->where('name', 'Live Nation CI')->sole();
    $user = User::query()->where('email', 'contact@livenation.ci')->sole();

    expect($user->promoter_id)->toBe($promoter->id)
        ->and($user->hasRole(Role::PromoterAdmin->value))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and(Hash::check('Promoteur2026ok', $user->password))->toBeTrue();

    $component->call('dismissCredentials')->assertDontSee('Promoteur2026ok');
});

it('génère le mot de passe et crée le compte plus tard pour un promoteur qui n’en a pas', function () {
    $promoter = Promoter::factory()->create(['name' => 'Sans compte']);

    $component = Livewire::test(PromotersTable::class)
        ->call('edit', $promoter->id)
        ->assertSet('createAccount', false)
        ->set('createAccount', true)
        ->set('account_name', 'Jean Yao')
        ->set('account_email', 'jean@sanscompte.ci')
        ->call('generatePassword', 'account_password');

    $password = $component->get('account_password');
    $component->call('save')->assertHasNoErrors()->assertSee($password);

    expect(User::query()->where('promoter_id', $promoter->id)->sole()->email)->toBe('jean@sanscompte.ci');

    Livewire::test(PromotersTable::class)->call('edit', $promoter->id)->assertDontSee('Créer le compte de connexion');
    Livewire::test(PromotersTable::class)->call('generatePassword', 'name')->assertStatus(400);
});
