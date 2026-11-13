<?php

use App\Enums\BusStatus;
use App\Enums\Direction;
use App\Enums\Role;
use App\Enums\StaffRole;
use App\Livewire\Company\Assignments;
use App\Livewire\Company\SalesReport;
use App\Livewire\Company\TransportPlan;
use App\Models\Bus;
use App\Models\Device;
use App\Models\Mission;
use App\Models\Staff;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->event = eventOnSale(seats: 10);
    $this->event->offers()->update(['bus_count' => 5]);
    paidOrder($this->event, 6);
    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $this->event->company_id]));

    Livewire::test(TransportPlan::class)->call('generate', $this->event->id);
    $this->mission = Mission::query()->where('direction', Direction::Outbound)->sole();
});

it('génère les missions depuis le Plan de transport', function () {
    expect(Mission::query()->count())->toBe(2)
        ->and($this->mission->outboundPasses()->count())->toBe(6);

    Livewire::test(TransportPlan::class)->assertSee('Actualiser les missions')->assertSee('Gare Siporex');
});

it('affecte bus, chauffeur, convoyeur et terminal à une mission', function () {
    $bus = Bus::factory()->create(['company_id' => $this->event->company_id, 'capacity' => 50]);
    $driver = Staff::factory()->role(StaffRole::Driver)->create(['company_id' => $this->event->company_id]);
    $conveyor = Staff::factory()->role(StaffRole::Conveyor)->create(['company_id' => $this->event->company_id]);
    $device = Device::factory()->create(['company_id' => $this->event->company_id]);

    Livewire::test(Assignments::class, ['eventId' => $this->event->id])
        ->call('assign', $this->mission->id, 'bus_id', (string) $bus->id)
        ->call('assign', $this->mission->id, 'driver_id', (string) $driver->id)
        ->call('assign', $this->mission->id, 'conveyor_id', (string) $conveyor->id)
        ->call('assign', $this->mission->id, 'device_id', (string) $device->id)
        ->assertHasNoErrors();

    expect($this->mission->fresh()->isFullyStaffed())->toBeTrue()
        ->and($this->mission->fresh()->device_id)->toBe($device->id);
});

it('refuse un mauvais rôle, un bus en panne, un bus trop petit et une autre compagnie', function (Closure $resource, string $field) {
    Livewire::test(Assignments::class, ['eventId' => $this->event->id])
        ->call('assign', $this->mission->id, $field, (string) $resource($this->event)->id)
        ->assertHasErrors("mission.{$this->mission->id}.{$field}");

    expect($this->mission->fresh()->{$field})->toBeNull();
})->with([
    'convoyeur comme chauffeur' => [fn ($event) => Staff::factory()->role(StaffRole::Conveyor)->create(['company_id' => $event->company_id]), 'driver_id'],
    'bus en panne' => [fn ($event) => Bus::factory()->create(['company_id' => $event->company_id, 'status' => BusStatus::Broken]), 'bus_id'],
    'bus trop petit' => [fn ($event) => Bus::factory()->create(['company_id' => $event->company_id, 'capacity' => 5]), 'bus_id'],
    'bus d\'une autre compagnie' => [fn ($event) => Bus::factory()->create(), 'bus_id'],
]);

it('refuse de mettre le même bus sur deux missions simultanées', function () {
    paidOrder($this->event, 9);
    Livewire::test(TransportPlan::class)->call('generate', $this->event->id);
    [$first, $second] = Mission::query()->where('direction', Direction::Outbound)->orderBy('sequence')->get();
    $bus = Bus::factory()->create(['company_id' => $this->event->company_id]);

    Livewire::test(Assignments::class, ['eventId' => $this->event->id])
        ->call('assign', $first->id, 'bus_id', (string) $bus->id)
        ->call('assign', $second->id, 'bus_id', (string) $bus->id)
        ->assertHasErrors("mission.{$second->id}.bus_id");

    // Le même bus peut faire l'aller et le retour.
    $return = Mission::query()->where('direction', Direction::Return)->first();
    Livewire::test(Assignments::class, ['eventId' => $this->event->id, 'direction' => 'retour'])
        ->call('assign', $return->id, 'bus_id', (string) $bus->id)
        ->assertHasNoErrors();
});

it('gère les bus de réserve de l\'événement', function () {
    $bus = Bus::factory()->create(['company_id' => $this->event->company_id]);

    Livewire::test(Assignments::class, ['eventId' => $this->event->id])
        ->set('reserveBusId', $bus->id)
        ->set('waitingPlace', 'Parking P5 Ebimpé')
        ->call('addReserve')
        ->assertHasNoErrors()
        ->assertSee('Parking P5 Ebimpé');

    expect($this->event->reserveBuses()->sole()->bus_id)->toBe($bus->id);
});

it('affiche les ventes à la direction commerciale et exporte en Excel (CO-01 à CO-06)', function () {
    Excel::fake();
    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => $this->event->company_id]));

    Livewire::test(SalesReport::class)
        ->assertSet('eventId', $this->event->id)
        ->assertSee('Pass vendus')
        ->assertSee('sur 50 places ouvertes')
        ->assertSee('18 000')
        ->assertSee('16 200')
        ->assertSee('60 %')
        ->call('export');

    Excel::assertDownloaded('ventes-'.Str::slug($this->event->name).'.xlsx');
});

it('cloisonne les écrans entre directions', function () {
    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => $this->event->company_id]))
        ->get('/compagnie/affectations')->assertForbidden();

    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $this->event->company_id]))
        ->get('/compagnie/ventes')->assertForbidden();
});
