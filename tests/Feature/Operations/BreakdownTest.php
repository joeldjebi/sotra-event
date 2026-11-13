<?php

use App\Enums\AlertStatus;
use App\Enums\BusStatus;
use App\Enums\Direction;
use App\Enums\MissionStatus;
use App\Enums\PassStatus;
use App\Enums\Role;
use App\Enums\StaffRole;
use App\Enums\TechTeamStatus;
use App\Http\Controllers\Api\V1\Webhooks\InboundSmsController;
use App\Livewire\Company\Assignments;
use App\Livewire\Company\BreakdownsTable;
use App\Livewire\Company\LiveOperations;
use App\Models\BreakdownAlert;
use App\Models\Bus;
use App\Models\Depot;
use App\Models\Intervention;
use App\Models\Mission;
use App\Models\OutboundMessage;
use App\Models\SmsMessage;
use App\Models\Staff;
use App\Models\TechTeam;
use App\Services\Operations\MissionPlanner;
use App\Services\Sales\PassTokenService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->event = eventOnSale(seats: 10);
    $this->event->offers()->update(['bus_count' => 5]);
    $this->order = paidOrder($this->event, 2);
    app(MissionPlanner::class)->plan($this->event);
    $company = $this->event->company_id;

    $this->bus = Bus::factory()->create(['company_id' => $company, 'number' => '27']);
    $this->conveyor = Staff::factory()->role(StaffRole::Conveyor)->create(['company_id' => $company, 'first_name' => 'Kouassi', 'last_name' => 'Yao']);
    $this->mission = Mission::query()->where('direction', Direction::Outbound)->sole();
    $this->mission->update(['bus_id' => $this->bus->id, 'conveyor_id' => $this->conveyor->id, 'status' => MissionStatus::OnRoute, 'actual_departure_at' => now()]);

    $this->team = TechTeam::factory()->create(['company_id' => $company, 'code' => 'T3', 'location' => Point::makeGeodetic(5.40, -4.06)]);
    $this->technician = Staff::factory()->role(StaffRole::Technician)->create(['company_id' => $company, 'tech_team_id' => $this->team->id]);
    $this->event->techTeams()->create(['tech_team_id' => $this->team->id, 'sector' => 'Secteur Nord']);

    $depot = Depot::factory()->create(['company_id' => $company, 'location' => Point::makeGeodetic(5.34, -4.07)]);
    $this->reserve = Bus::factory()->create(['company_id' => $company, 'number' => '41', 'depot_id' => $depot->id, 'status' => BusStatus::Reserve]);
    $this->event->reserveBuses()->create(['bus_id' => $this->reserve->id, 'waiting_place' => 'Dépôt Yopougon']);
});

function boardAll(): void
{
    Sanctum::actingAs(test()->conveyor, ['conveyor']);

    foreach (test()->order->passes as $pass) {
        test()->postJson('/api/v1/scans', ['mission_id' => test()->mission->id, 'input' => app(PassTokenService::class)->sign($pass->fresh()), 'client_uuid' => (string) Str::uuid()]);
    }
}

function reportBreakdown(string $type = 'moteur'): TestResponse
{
    Sanctum::actingAs(test()->conveyor, ['conveyor']);

    return test()->post('/api/v1/breakdowns', [
        'mission_id' => test()->mission->id, 'type' => $type, 'details' => 'Voyant de température rouge',
        'latitude' => 5.42, 'longitude' => -4.07, 'accuracy_m' => 8,
        'photos' => [UploadedFile::fake()->image('panne.jpg')],
    ], ['Accept' => 'application/json']);
}

function takeAs(Staff $technician, BreakdownAlert $alert): TestResponse
{
    Sanctum::actingAs($technician, ['tech']);

    return test()->postJson("/api/v1/tech/alerts/{$alert->id}/take");
}

it('signale une panne urgente quand des passagers sont à bord et prévient les techniciens (CV-15 à CV-18, ST-06)', function () {
    boardAll();

    reportBreakdown()->assertCreated()->assertJsonPath('data.urgency', 'urgent')->assertJsonPath('data.passengers_on_board', 2);

    $alert = BreakdownAlert::query()->sole();
    expect($this->mission->fresh()->status)->toBe(MissionStatus::Incident)
        ->and($alert->photos)->toHaveCount(1)
        ->and(OutboundMessage::query()->where('recipient', 'staff:'.$this->technician->id)->where('template', 'like', 'Panne urgente%')->exists())->toBeTrue();

    Storage::disk('public')->assertExists($alert->photos[0]);
});

it('signale une panne normale à vide, mais toujours urgente en cas d\'accident', function () {
    reportBreakdown()->assertJsonPath('data.urgency', 'normal');

    BreakdownAlert::query()->delete();
    $this->mission->update(['status' => MissionStatus::OnRoute]);
    reportBreakdown('accident')->assertJsonPath('data.urgency', 'urgent');
});

it('liste les alertes avec distance et temps, et une seule équipe peut les prendre (ST-02 à ST-04)', function () {
    boardAll();
    reportBreakdown();
    $alert = BreakdownAlert::query()->sole();

    Sanctum::actingAs($this->technician, ['tech']);
    $this->getJson('/api/v1/tech/alerts')
        ->assertOk()
        ->assertJsonPath('data.0.urgency_label', 'Urgent · passagers à bord')
        ->assertJsonPath('data.0.bus', '27')
        ->assertJsonPath('data.0.distance_km', fn ($km) => $km > 1 && $km < 5)
        ->assertJsonPath('data.0.reporter.name', 'K. Yao');

    takeAs($this->technician, $alert)->assertCreated()->assertJsonPath('data.number', 'PN-0001');
    expect($this->team->fresh()->status)->toBe(TechTeamStatus::OnIntervention);

    $other = TechTeam::factory()->create(['company_id' => $this->event->company_id, 'code' => 'T1']);
    $this->event->techTeams()->create(['tech_team_id' => $other->id, 'sector' => 'Sud']);
    $otherTech = Staff::factory()->role(StaffRole::Technician)->create(['company_id' => $this->event->company_id, 'tech_team_id' => $other->id]);

    takeAs($otherTech, $alert)->assertStatus(409)->assertJsonPath('code', 'ALERT_ALREADY_TAKEN')->assertJsonPath('message', 'Déjà pris en charge par l\'équipe T3.');
    $this->getJson('/api/v1/tech/alerts')->assertJsonPath('data.0.taken_by', 'T3')->assertJsonPath('data.0.taken_by_me', false);
});

it('refuse la prise en charge à une équipe non mobilisée', function () {
    reportBreakdown();
    $stranger = Staff::factory()->role(StaffRole::Technician)->create([
        'company_id' => $this->event->company_id,
        'tech_team_id' => TechTeam::factory()->create(['company_id' => $this->event->company_id])->id,
    ]);

    takeAs($stranger, BreakdownAlert::query()->sole())->assertForbidden()->assertJsonPath('code', 'TEAM_NOT_ASSIGNED');
});

it('enchaîne les étapes dans l\'ordre puis clôture « réparé » : le bus reprend sa route (ST-09, ST-13)', function () {
    reportBreakdown();
    $id = takeAs($this->technician, BreakdownAlert::query()->sole())->json('data.id');

    $this->postJson("/api/v1/tech/interventions/{$id}/steps", ['step' => 'sur_place'])->assertOk()->assertJsonPath('data.step', 'sur_place');
    $this->postJson("/api/v1/tech/interventions/{$id}/steps", ['step' => 'en_route'])->assertStatus(409)->assertJsonPath('code', 'INTERVENTION_STEP_INVALID');

    $this->postJson("/api/v1/tech/interventions/{$id}/close", ['result' => 'repare', 'diagnosis' => 'Durite resserrée', 'duration_min' => 15])
        ->assertOk()->assertJsonPath('data.step', 'cloturee')->assertJsonPath('data.result', 'repare');

    expect($this->mission->fresh()->status)->toBe(MissionStatus::OnRoute)
        ->and(BreakdownAlert::query()->sole()->status)->toBe(AlertStatus::Closed)
        ->and($this->team->fresh()->status)->toBe(TechTeamStatus::Available);
});

it('remplace le bus : Pass transférés, scan de contrôle, passagers prévenus (RG-20, ST-15)', function () {
    boardAll();
    reportBreakdown();
    $id = takeAs($this->technician, BreakdownAlert::query()->sole())->json('data.id');

    $this->getJson('/api/v1/tech/reserve-buses?alert='.BreakdownAlert::query()->value('id'))
        ->assertOk()->assertJsonPath('data.0.number', '41')->assertJsonPath('data.0.distance_km', fn ($km) => $km > 0);

    $this->postJson("/api/v1/tech/interventions/{$id}/close", ['result' => 'remplace'])->assertUnprocessable()->assertJsonValidationErrors('replacement_bus_id');
    $this->postJson("/api/v1/tech/interventions/{$id}/close", ['result' => 'remplace', 'replacement_bus_id' => $this->reserve->id, 'towing_requested' => true, 'diagnosis' => 'Durite percée'])
        ->assertOk()->assertJsonPath('data.replacement_bus', '41')->assertJsonPath('data.towing_requested', true);

    $replacement = Mission::query()->where('bus_id', $this->reserve->id)->sole();
    expect($replacement->conveyor_id)->toBe($this->conveyor->id)
        ->and($this->mission->fresh()->replaced_by_mission_id)->toBe($replacement->id)
        ->and($this->bus->fresh()->status)->toBe(BusStatus::Broken)
        ->and($this->event->reserveBuses()->count())->toBe(0)
        ->and($this->order->passes()->pluck('outbound_mission_id')->unique()->all())->toBe([$replacement->id])
        ->and(SmsMessage::query()->where('body', 'like', '%Le bus 41 vous prend en charge%')->count())->toBe(2);

    // Scan de contrôle à la montée dans le bus de réserve : vert, sans consommer le segment.
    Sanctum::actingAs($this->conveyor, ['conveyor']);
    $pass = $this->order->passes->first();
    $scan = fn () => $this->postJson('/api/v1/scans', ['mission_id' => $replacement->id, 'input' => app(PassTokenService::class)->sign($pass->fresh()), 'client_uuid' => (string) Str::uuid()]);

    $scan()->assertJsonPath('data.result', 'vert')->assertJsonPath('data.on_board', 1);
    $scan()->assertJsonPath('data.result', 'rouge');
    expect($pass->fresh()->status)->toBe(PassStatus::OutboundUsed);
});

it('accepte une alerte par SMS en mode dégradé (CV-19)', function () {
    boardAll();
    $sms = fn (string $text, array $headers) => $this->postJson('/api/v1/sms/inbound', ['from' => $this->conveyor->phone, 'text' => $text], $headers);

    $sms('PANNE 27 MOTEUR', ['X-Sms-Secret' => 'faux'])->assertUnauthorized();
    $sms('bonjour', ['X-Sms-Secret' => InboundSmsController::secret()])->assertJsonPath('handled', false);
    $sms('PANNE 27 MOTEUR voyant rouge', ['X-Sms-Secret' => InboundSmsController::secret()])->assertJsonPath('handled', true);

    expect(BreakdownAlert::query()->sole())
        ->channel->toBe('sms')
        ->details->toBe('voyant rouge')
        ->passengers_on_board->toBe(2);
});

it('permet à l\'exploitation d\'envoyer une équipe et de remplacer le bus depuis le direct', function () {
    boardAll();
    reportBreakdown();
    $alert = BreakdownAlert::query()->sole();
    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $this->event->company_id]));

    Livewire::test(LiveOperations::class)
        ->assertSee('Panne signalée')
        ->assertSee('2 passagers à bord')
        ->set("dispatchTeam.{$alert->id}", $this->team->id)
        ->call('sendTeam', $alert->id)
        ->set("replacementBus.{$alert->id}", $this->reserve->id)
        ->call('replaceBus', $alert->id)
        ->assertSee('Bus 41 en remplacement');

    expect(Intervention::query()->sole()->tech_team_id)->toBe($this->team->id)
        ->and($this->mission->fresh()->replaced_by_mission_id)->not->toBeNull();

    Livewire::test(BreakdownsTable::class)->assertSee('Moteur / surchauffe')->call('show', $alert->id)->assertSee('Kouassi Yao');
});

it('mobilise une équipe sur l\'événement et réserve l\'écran Pannes à l\'exploitation', function () {
    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $this->event->company_id]));
    $team = TechTeam::factory()->create(['company_id' => $this->event->company_id, 'code' => 'T9']);

    Livewire::test(Assignments::class, ['eventId' => $this->event->id])
        ->set('techTeamId', $team->id)->set('sector', 'Secteur Sud')->call('addTechTeam')->assertHasNoErrors()->assertSee('Équipe T9');

    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => $this->event->company_id]))->get('/compagnie/pannes')->assertForbidden();
});

it('affiche les bus en route et les pannes en cours sur le tableau de bord ACCES EVENT', function () {
    boardAll();
    reportBreakdown();

    $this->actingAs(userWithRole(Role::SuperAdmin))
        ->get('/admin')
        ->assertOk()
        ->assertSee('1 en cours · dont 1 urgente(s)');
});
