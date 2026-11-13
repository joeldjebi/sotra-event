<?php

use App\Actions\Passes\TransferPass;
use App\Enums\DeviceStatus;
use App\Enums\Direction;
use App\Enums\MissionStatus;
use App\Enums\Offer;
use App\Enums\OrderStatus;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\StaffRole;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Mission;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Scan;
use App\Models\SmsMessage;
use App\Models\Staff;
use App\Services\Operations\MissionPlanner;
use App\Services\Sales\PassTokenService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->event = eventOnSale(seats: 10);
    $this->event->offers()->update(['bus_count' => 5]);
    $this->event->dropZones()->create(['name' => 'Parking P3 · porte Nord', 'usage' => 'depose', 'location' => Point::makeGeodetic(5.4950, -4.0780), 'radius_m' => 200]);
    $this->order = paidOrder($this->event, 3);
    app(MissionPlanner::class)->plan($this->event);

    $this->mission = Mission::query()->where('direction', Direction::Outbound)->sole();
    $this->conveyor = Staff::factory()->role(StaffRole::Conveyor)->create(['company_id' => $this->event->company_id]);
    $this->mission->update(['conveyor_id' => $this->conveyor->id]);
    Sanctum::actingAs($this->conveyor, ['conveyor']);
});

function scanInput(string $input, ?int $missionId = null): TestResponse
{
    return test()->postJson('/api/v1/scans', ['mission_id' => $missionId ?? test()->mission->id, 'input' => $input, 'client_uuid' => (string) Str::uuid()]);
}

function tokenOf(Pass $pass): string
{
    return app(PassTokenService::class)->sign($pass->fresh());
}

it('liste les missions du jour du convoyeur (CV-01 à CV-03)', function () {
    $this->travelTo($this->mission->planned_departure_at->copy()->subHours(2));

    $this->getJson('/api/v1/me/missions')
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->mission->id)
        ->assertJsonPath('data.0.direction', 'aller')
        ->assertJsonPath('data.0.on_board', 0)
        ->assertJsonPath('data.0.capacity', 10);
});

it('fournit le manifeste pour le contrôle hors ligne', function () {
    $this->getJson("/api/v1/missions/{$this->mission->id}/manifest")
        ->assertOk()
        ->assertJsonPath('data.public_key', app(PassTokenService::class)->publicKeyBase64())
        ->assertJsonCount(3, 'data.passes')
        ->assertJsonPath('data.passes.0.expected', true)
        ->assertJsonPath('data.passes.0.token_version', 1);
});

it('refuse la mission d\'un autre convoyeur', function () {
    Sanctum::actingAs(Staff::factory()->role(StaffRole::Conveyor)->create(['company_id' => $this->event->company_id]), ['conveyor']);

    $this->getJson("/api/v1/missions/{$this->mission->id}/manifest")->assertForbidden()->assertJsonPath('code', 'MISSION_NOT_ASSIGNED');
});

it('valide un Pass une seule fois par segment (CV-08, RG-03)', function () {
    $pass = $this->order->passes->first();

    scanInput(tokenOf($pass))->assertOk()
        ->assertJsonPath('data.result', 'vert')
        ->assertJsonPath('data.message', 'Pass valide')
        ->assertJsonPath('data.pass.boarding_point', 'Yopougon')
        ->assertJsonPath('data.on_board', 1)
        ->assertJsonPath('data.capacity', 10);

    expect($pass->fresh()->status)->toBe(PassStatus::OutboundUsed)
        ->and($this->mission->fresh()->status)->toBe(MissionStatus::Boarding);

    scanInput(tokenOf($pass))->assertJsonPath('data.result', 'rouge')->assertJsonPath('data.reason', 'deja_utilise');
    scanInput($pass->code)->assertJsonPath('data.reason', 'deja_utilise');
});

it('accepte la saisie manuelle du code (CV-10)', function () {
    scanInput(strtolower($this->order->passes->first()->code))->assertJsonPath('data.result', 'vert');
});

it('refuse un QR falsifié, un code inconnu et l\'ancien QR d\'un Pass offert', function () {
    $pass = $this->order->passes->first();
    $oldToken = tokenOf($pass);
    app(TransferPass::class)->handle($pass, $pass->holder, '0505050505');

    scanInput('ET1.faux.jeton')->assertJsonPath('data.reason', 'falsifie');
    scanInput('ET-2611-9999-YOP')->assertJsonPath('data.reason', 'code_inconnu');
    scanInput($oldToken)->assertJsonPath('data.reason', 'qr_perime');
    scanInput(tokenOf($pass))->assertJsonPath('data.result', 'vert');
});

it('signale en orange un Pass prévu dans un autre bus du même point et l\'y réaffecte', function () {
    $other = Mission::query()->create($this->mission->only(['event_id', 'company_id', 'boarding_point_id', 'offer', 'direction', 'seats', 'planned_departure_at', 'status']) + ['sequence' => 2, 'conveyor_id' => $this->conveyor->id]);
    $pass = $this->order->passes->first();

    scanInput(tokenOf($pass), $other->id)->assertJsonPath('data.result', 'orange')->assertJsonPath('data.reason', 'autre_bus');

    expect($pass->fresh()->outbound_mission_id)->toBe($other->id);
});

it('refuse un Pass d\'une autre offre ou d\'un trajet non acheté (RG-09)', function () {
    $this->event->offers()->create(['offer' => Offer::Premium, 'bus_count' => 1, 'seats_per_bus' => 10]);
    $this->event->prices()->create(['zone' => 1, 'offer' => Offer::Premium, 'pass_type' => PassType::RoundTrip, 'price' => 5000]);
    $premium = Pass::factory()->create([
        'order_id' => $this->order->id, 'event_id' => $this->event->id, 'boarding_point_id' => $this->mission->boarding_point_id,
        'holder_id' => $this->order->customer_id, 'offer' => Offer::Premium, 'code' => 'ET-2611-0900-YOP',
    ]);
    $returnOnly = Pass::factory()->create([
        'order_id' => $this->order->id, 'event_id' => $this->event->id, 'boarding_point_id' => $this->mission->boarding_point_id,
        'holder_id' => $this->order->customer_id, 'pass_type' => PassType::Return, 'code' => 'ET-2611-0901-YOP',
    ]);

    scanInput(tokenOf($premium))->assertJsonPath('data.reason', 'offre_differente');
    scanInput(tokenOf($returnOnly))->assertJsonPath('data.reason', 'segment_non_achete');
});

it('bloque la montée quand le bus est plein (CV-09)', function () {
    $this->mission->update(['seats' => 2]);
    [$first, $second, $third] = $this->order->passes;

    scanInput(tokenOf($first))->assertJsonPath('data.result', 'vert');
    scanInput(tokenOf($second))->assertJsonPath('data.result', 'vert');
    scanInput(tokenOf($third))->assertJsonPath('data.result', 'rouge')->assertJsonPath('data.reason', 'bus_plein');
});

it('synchronise 500 scans hors ligne sans perte ni doublon et signale les conflits (lot 3, NF-01, NF-09)', function () {
    $this->event->offers()->update(['bus_count' => 20, 'seats_per_bus' => 50]);
    $this->mission->update(['seats' => 600]);
    $order = Order::factory()->create(['customer_id' => Customer::factory()->create()->id, 'event_id' => $this->event->id, 'boarding_point_id' => $this->mission->boarding_point_id, 'status' => OrderStatus::Paid]);
    $passes = Pass::factory()->count(500)->sequence(fn ($sequence) => ['code' => sprintf('ET-2611-%04d-YOP', 5000 + $sequence->index)])->create([
        'order_id' => $order->id, 'event_id' => $this->event->id, 'boarding_point_id' => $this->mission->boarding_point_id,
        'holder_id' => $order->customer_id, 'outbound_mission_id' => $this->mission->id,
    ]);

    $start = now()->subHour();
    $scans = $passes->values()->map(fn (Pass $pass, int $index) => [
        'mission_id' => $this->mission->id,
        'input' => tokenOf($pass),
        'client_uuid' => (string) Str::uuid(),
        'scanned_at' => $start->copy()->addSeconds($index * 2)->toIso8601String(),
    ])->all();

    foreach (array_chunk($scans, 250) as $chunk) {
        $this->postJson('/api/v1/scans/sync', ['scans' => $chunk])->assertOk()->assertJsonPath('data.synced', 250);
    }

    // Renvoi du même lot (réseau coupé avant l'accusé de réception) : aucun doublon.
    $this->postJson('/api/v1/scans/sync', ['scans' => array_slice($scans, 0, 250)])->assertOk();

    expect(Scan::query()->where('mission_id', $this->mission->id)->whereIn('result', ['vert', 'orange'])->count())->toBe(500)
        ->and(Pass::query()->whereIn('id', $passes->pluck('id'))->where('status', PassStatus::OutboundUsed)->count())->toBe(500);

    // Un second appareil avait scanné hors ligne 5 de ces Pass PLUS TÔT (copie de QR) : il l'emporte, les premiers sont signalés.
    $copies = collect($scans)->take(5)->map(fn (array $scan) => ['client_uuid' => (string) Str::uuid(), 'scanned_at' => $start->copy()->subMinutes(10)->toIso8601String()] + $scan)->all();
    $this->postJson('/api/v1/scans/sync', ['scans' => $copies])->assertOk()->assertJsonPath('data.conflicts', 0);

    expect(Scan::query()->where('conflict', true)->count())->toBe(5)
        ->and(Scan::query()->where('mission_id', $this->mission->id)->whereIn('result', ['vert', 'orange'])->where('conflict', false)->count())->toBe(500);
});

it('confirme le départ puis détecte l\'arrivée dans la zone de dépose (CV-05, CV-14)', function () {
    $this->postJson("/api/v1/missions/{$this->mission->id}/arrive")->assertStatus(409)->assertJsonPath('code', 'MISSION_STATUS_INVALID');

    $this->postJson("/api/v1/missions/{$this->mission->id}/depart")->assertOk()->assertJsonPath('data.status', 'en_route');
    expect($this->mission->fresh()->actual_departure_at)->not->toBeNull();

    $this->postJson("/api/v1/missions/{$this->mission->id}/positions", ['positions' => [
        ['latitude' => 5.40, 'longitude' => -4.08, 'recorded_at' => now()->subSeconds(30)->toIso8601String(), 'speed_kmh' => 45],
    ]])->assertOk()->assertJsonPath('data.status', 'en_route');

    $this->postJson("/api/v1/missions/{$this->mission->id}/positions", ['positions' => [
        ['latitude' => 5.4951, 'longitude' => -4.0781, 'recorded_at' => now()->toIso8601String(), 'accuracy_m' => 8],
    ]])->assertOk()->assertJsonPath('data.status', 'arrivee');

    expect($this->mission->fresh()->arrived_at)->not->toBeNull();
});

it('refuse un terminal bloqué et note la synchronisation d\'un terminal enregistré', function () {
    $device = Device::factory()->create(['company_id' => $this->event->company_id, 'serial_number' => 'PDA-1']);

    $this->withHeaders(['X-Device-Serial' => 'PDA-1', 'X-App-Version' => '1.4.0'])->getJson('/api/v1/me/missions')->assertOk();
    expect($device->fresh())->last_sync_at->not->toBeNull()->app_version->toBe('1.4.0');

    $device->update(['status' => DeviceStatus::Blocked]);
    $this->withHeaders(['X-Device-Serial' => 'PDA-1'])->getJson('/api/v1/me/missions')->assertForbidden()->assertJsonPath('code', 'DEVICE_BLOCKED');
});

it('donne au spectateur la chronologie et la position de son bus (SP-18)', function () {
    $pass = $this->order->passes->first();
    scanInput(tokenOf($pass));
    $this->postJson("/api/v1/missions/{$this->mission->id}/depart");
    $this->postJson("/api/v1/missions/{$this->mission->id}/positions", ['positions' => [
        ['latitude' => 5.40, 'longitude' => -4.08, 'recorded_at' => now()->toIso8601String(), 'speed_kmh' => 40],
    ]]);

    Sanctum::actingAs($pass->holder, ['customer']);

    $this->getJson("/api/v1/passes/{$pass->code}/trip")
        ->assertOk()
        ->assertJsonPath('data.steps.0.done', true)
        ->assertJsonPath('data.steps.1.label', 'Départ du bus')
        ->assertJsonPath('data.steps.1.done', true)
        ->assertJsonPath('data.steps.2.current', true)
        ->assertJsonPath('data.live.status', 'en_route')
        ->assertJsonPath('data.live.latitude', 5.4)
        ->assertJsonPath('data.live.eta_minutes', fn ($eta) => $eta > 0);
});

it('envoie les rappels J-1 et H-2 une seule fois (SP-21)', function () {
    $this->travelTo($this->event->boardingPoints->first()->departure_at->copy()->subHours(20));
    $this->artisan('passes:remind')->expectsOutputToContain('3 rappel(s)');
    $this->artisan('passes:remind')->expectsOutputToContain('0 rappel(s)');

    $this->travelTo($this->event->boardingPoints->first()->departure_at->copy()->subMinutes(90));
    $this->artisan('passes:remind')->expectsOutputToContain('3 rappel(s)');

    expect(SmsMessage::query()->where('body', 'like', '%rappel%')->count())->toBe(6);
});
