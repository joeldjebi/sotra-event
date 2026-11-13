<?php

use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\PassType;
use App\Enums\Role;
use App\Livewire\Admin\Events\BoardingPointsStep;
use App\Livewire\Admin\Events\InformationStep;
use App\Livewire\Admin\Events\PricingStep;
use App\Livewire\Admin\Events\PublicationStep;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Event;
use App\Models\Promoter;
use App\Models\Venue;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(userWithRole(Role::Operator));
    $this->venue = Venue::factory()->create(['location' => Point::makeGeodetic(5.4925, -4.0791)]);
});

/**
 * Événement en brouillon au stade (fin prévue 23 h) avec ses relations chargées.
 */
function draftEvent(array $attributes = []): Event
{
    return Event::factory()->create(['venue_id' => test()->venue->id] + $attributes);
}

it('crée un brouillon et reprend la commission par défaut de la compagnie (AD-01)', function () {
    $company = Company::factory()->create(['default_commission_rate' => 12]);
    $promoter = Promoter::factory()->create();
    $lead = userWithRole(Role::TransportLead, ['promoter_id' => $promoter->id]);

    $component = Livewire::test(InformationStep::class)
        ->set('name', 'Concert au stade d\'Ebimpé')
        ->set('venue_id', $this->venue->id)
        ->set('date', '2026-11-15')
        ->set('start_time', '18:00')
        ->set('end_time', '00:30')
        ->set('promoter_id', $promoter->id)
        ->set('transport_lead_id', $lead->id)
        ->set('company_id', $company->id)
        ->assertSet('commission_rate', '12')
        ->call('save')
        ->assertHasNoErrors();

    $event = Event::query()->sole();

    expect($event->status)->toBe(EventStatus::Draft)
        ->and($event->slug)->toBe('concert-au-stade-debimpe-2026-11-15')
        ->and($event->ends_at->toDateTimeString())->toBe('2026-11-16 00:30:00')
        ->and((float) $event->fee_rate)->toBe(2.0);

    $component->assertRedirect(route('admin.events.edit', ['event' => $event, 'etape' => 2]));
});

it('refuse un chef de projet qui n\'appartient pas au promoteur', function () {
    $lead = userWithRole(Role::TransportLead);

    Livewire::test(InformationStep::class)
        ->set('name', 'X')->set('venue_id', $this->venue->id)->set('date', '2026-11-15')
        ->set('promoter_id', Promoter::factory()->create()->id)
        ->set('transport_lead_id', $lead->id)
        ->set('company_id', Company::factory()->create()->id)
        ->call('save')
        ->assertHasErrors('transport_lead_id');
});

it('décale les horaires des points quand la date de l\'événement change', function () {
    $event = draftEvent(['starts_at' => '2026-11-15 18:00', 'ends_at' => '2026-11-15 23:00']);
    Livewire::test(BoardingPointsStep::class, ['event' => $event])
        ->set('commune_code', 'YOP')->set('place', 'Gare Siporex')->set('latitude', '5.3446')->set('longitude', '-4.0802')
        ->set('departure_time', '15:30')->set('return_time', '23:30')
        ->call('save');

    Livewire::test(InformationStep::class, ['event' => $event->fresh()])
        ->set('date', '2026-11-22')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->boardingPoints()->sole()->departure_at->toDateTimeString())->toBe('2026-11-22 15:30:00');
});

it('ajoute un point d\'embarquement avec distance et zone calculées (AD-02, RG-06)', function () {
    $event = draftEvent(['starts_at' => '2026-11-15 18:00', 'ends_at' => '2026-11-15 23:00']);

    Livewire::test(BoardingPointsStep::class, ['event' => $event])
        ->call('create')
        ->set('commune_code', 'ABO')
        ->set('place', 'Gare d\'Abobo')
        ->set('latitude', '5.4180')
        ->set('longitude', '-4.0200')
        ->set('departure_time', '15:45')
        ->set('return_time', '00:30')
        ->call('save')
        ->assertHasNoErrors();

    $point = $event->boardingPoints()->sole();

    expect($point->commune)->toBe('Abobo')
        ->and((float) $point->distance_km)->toEqualWithDelta(10.5, 0.3)
        ->and($point->zone)->toBe(1)
        ->and($point->return_departure_at->toDateTimeString())->toBe('2026-11-16 00:30:00')
        ->and($point->travel_minutes)->toBeGreaterThan(0)
        ->and($point->boardingCallAt()->format('H:i'))->toBe('15:30');
});

it('refuse un retour avant la fin prévue et un aller après le début (AD-06)', function () {
    $event = draftEvent(['starts_at' => '2026-11-15 18:00', 'ends_at' => '2026-11-15 23:00']);

    Livewire::test(BoardingPointsStep::class, ['event' => $event])
        ->set('commune_code', 'YOP')->set('place', 'Siporex')->set('latitude', '5.3446')->set('longitude', '-4.0802')
        ->set('departure_time', '18:30')
        ->set('return_time', '22:00')
        ->call('save')
        ->assertHasErrors(['departure_time', 'return_time']);
});

it('exige une justification pour forcer la zone', function () {
    $event = draftEvent(['starts_at' => '2026-11-15 18:00', 'ends_at' => '2026-11-15 23:00']);

    Livewire::test(BoardingPointsStep::class, ['event' => $event])
        ->set('commune_code', 'YOP')->set('place', 'Siporex')->set('latitude', '5.3446')->set('longitude', '-4.0802')
        ->set('zone_forced', true)->set('forced_zone', '1')
        ->call('save')
        ->assertHasErrors('zone_justification')
        ->set('zone_justification', 'Accès direct par l\'autoroute')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->boardingPoints()->sole())->zone->toBe(1)->zone_forced->toBeTrue();
});

it('préremplit la grille avec l\'exemple du CDC et l\'enregistre (AD-03)', function () {
    $event = draftEvent();

    Livewire::test(PricingStep::class, ['event' => $event])
        ->assertSet('prices.aller_retour.1.standard', '3000')
        ->assertSet('prices.aller_simple.1.standard', '2000')
        ->set('activeOffers', ['standard', 'premium'])
        ->call('save')
        ->assertHasNoErrors();

    expect($event->offers()->count())->toBe(2)
        ->and($event->prices()->where('pass_type', PassType::RoundTrip)->where('zone', 2)->where('offer', Offer::Premium)->value('price'))->toBe(5500)
        ->and($event->prices()->where('offer', Offer::Gold)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'pricing_updated')->exists())->toBeTrue();
});

it('exige l\'aller-retour des zones utilisées et applique RG-07 aux prix simples', function () {
    $event = draftEvent(['starts_at' => '2026-11-15 18:00', 'ends_at' => '2026-11-15 23:00']);
    $event->boardingPoints()->create([
        'commune' => 'Abobo', 'commune_code' => 'ABO', 'place' => 'Gare', 'location' => Point::makeGeodetic(5.418, -4.02),
        'distance_km' => 10, 'zone' => 1, 'departure_at' => '2026-11-15 15:00', 'return_departure_at' => '2026-11-15 23:30',
    ]);

    Livewire::test(PricingStep::class, ['event' => $event])
        ->set('activeOffers', ['standard'])
        ->set('prices.aller_retour.1.standard', '')
        ->call('save')
        ->assertHasErrors('prices.aller_retour.1.standard')
        ->set('prices.aller_retour.1.standard', '3000')
        ->set('prices.aller_simple.1.standard', '1500')
        ->call('save')
        ->assertHasErrors('prices.aller_simple.1.standard')
        ->set('prices.aller_simple.1.standard', '2000')
        ->call('save')
        ->assertHasNoErrors();
});

it('enregistre les canaux, le visuel et les zones de dépose (AD-04)', function () {
    Storage::fake('public');
    $event = draftEvent();

    Livewire::test(PublicationStep::class, ['event' => $event])
        ->set('channels', ['web', 'app', 'pdv'])
        ->call('saveChannels')
        ->assertHasErrors('channels.2')
        ->set('channels', ['web', 'whatsapp'])
        ->set('visual', UploadedFile::fake()->image('affiche.jpg'))
        ->call('saveChannels')
        ->assertHasNoErrors()
        ->call('createZone')
        ->set('zone_name', 'Parking P5 · porte Est')
        ->set('zone_usage', 'reprise')
        ->set('zone_time', '00:30')
        ->set('zone_latitude', '5.4915')
        ->set('zone_longitude', '-4.0760')
        ->call('saveZone')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event->channels)->toBe(['web', 'whatsapp'])
        ->and($event->visual_path)->not->toBeNull()
        ->and($event->dropZones()->sole()->scheduled_at->toDateTimeString())->toBe($event->starts_at->copy()->addDay()->toDateString().' 00:30:00');

    Storage::disk('public')->assertExists($event->visual_path);
});

it('verrouille informations, points et grille une fois l\'événement proposé', function () {
    $event = draftEvent(['status' => EventStatus::Proposed]);

    Livewire::test(InformationStep::class, ['event' => $event])->call('save')->assertForbidden();
    Livewire::test(BoardingPointsStep::class, ['event' => $event])->call('create')->assertForbidden();
    Livewire::test(PricingStep::class, ['event' => $event])->call('save')->assertForbidden();
});

it('affiche les 4 étapes de configuration', function (int $step, string $text) {
    $event = draftEvent();

    $this->get(route('admin.events.edit', ['event' => $event, 'etape' => $step]))->assertOk()->assertSee($text);
})->with([[1, 'Nom de l\'événement'], [2, 'Ajouter un point'], [3, 'Offres proposées'], [4, 'Canaux de vente']]);
