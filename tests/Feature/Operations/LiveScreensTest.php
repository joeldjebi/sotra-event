<?php

use App\Enums\Direction;
use App\Enums\MissionStatus;
use App\Enums\Role;
use App\Livewire\Company\LiveOperations;
use App\Livewire\Site\Trip;
use App\Models\Bus;
use App\Models\Mission;
use App\Services\Operations\MissionPlanner;
use Clickbar\Magellan\Data\Geometries\Point;
use Livewire\Livewire;

beforeEach(function () {
    $this->event = eventOnSale(seats: 10);
    $this->order = paidOrder($this->event, 2);
    app(MissionPlanner::class)->plan($this->event);
    $this->mission = Mission::query()->where('direction', Direction::Outbound)->sole();
    $this->mission->update([
        'status' => MissionStatus::OnRoute,
        'actual_departure_at' => now(),
        'bus_id' => Bus::factory()->create(['company_id' => $this->event->company_id, 'number' => '12'])->id,
    ]);
    $this->mission->positions()->create(['recorded_at' => now(), 'location' => Point::makeGeodetic(5.40, -4.08), 'speed_kmh' => 40]);
});

it('affiche l\'exploitation en direct avec la carte des bus', function () {
    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $this->event->company_id]));

    Livewire::test(LiveOperations::class)
        ->assertSet('eventId', $this->event->id)
        ->assertSee('1 en route')
        ->assertSee('Bus 12')
        ->assertSee('min de l\'arrivée')
        ->assertSeeHtml('data-map=');

    $this->get('/compagnie/exploitation-en-direct')->assertOk();
});

it('réserve l\'exploitation en direct au profil Exploitation', function () {
    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => $this->event->company_id]))
        ->get('/compagnie/exploitation-en-direct')->assertForbidden();
});

it('montre au spectateur la chronologie et l\'arrivée estimée de son bus', function () {
    $pass = $this->order->passes->first();
    $this->actingAs($pass->holder, 'customer');

    Livewire::test(Trip::class)
        ->assertSee('Départ du bus')
        ->assertSee('Bus 12')
        ->assertSee('avant l\'arrivée', false)
        ->assertSee($pass->code);
});

it('invite un visiteur à se connecter pour suivre son bus', function () {
    $this->get('/mon-trajet')->assertOk()->assertSee('Suivez votre bus en direct');
});
