<?php

use App\Enums\DropZoneUsage;
use App\Enums\EventStatus;
use App\Enums\NegotiationSubject;
use App\Enums\Offer;
use App\Enums\PassType;
use App\Enums\Role;
use App\Livewire\Admin\Events\BoardingPointsStep;
use App\Livewire\Admin\Events\StatusPanel;
use App\Livewire\Company\DefaultLines;
use App\Models\Company;
use App\Models\CompanyLine;
use App\Models\Event;
use App\Models\Promoter;
use App\Services\CompanyDefaultLines;
use App\Services\EventReadiness;
use Clickbar\Magellan\Data\Geometries\Point;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $promoter = Promoter::factory()->create();

    $this->event = Event::factory()->create([
        'company_id' => $this->company->id,
        'promoter_id' => $promoter->id,
        'transport_lead_id' => userWithRole(Role::TransportLead, ['promoter_id' => $promoter->id])->id,
        'starts_at' => '2026-12-15 18:00',
        'ends_at' => '2026-12-15 23:00',
    ]);
});

/**
 * Grille et publication complètes ; les points d'embarquement restent à créer.
 */
function prepareEventWithoutPoints(Event $event, array $offers = [Offer::Standard]): void
{
    foreach ($offers as $offer) {
        $event->offers()->create(['offer' => $offer]);

        foreach ([1, 2, 3] as $zone) {
            $event->prices()->create(['zone' => $zone, 'offer' => $offer, 'pass_type' => PassType::RoundTrip, 'price' => 3500]);
        }
    }

    $event->dropZones()->createMany([
        ['name' => 'P3', 'usage' => DropZoneUsage::DropOff, 'location' => Point::makeGeodetic(5.49, -4.07)],
        ['name' => 'P5', 'usage' => DropZoneUsage::PickUp, 'location' => Point::makeGeodetic(5.49, -4.07)],
    ]);
}

it('permet à l\'exploitation de la compagnie de définir une ligne et les places par bus', function () {
    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $this->company->id]));
    CompanyLine::factory()->create(['commune_code' => 'ABO', 'place' => 'Gare d\'une autre compagnie']);

    Livewire::test(DefaultLines::class)
        ->assertSee('Abobo')
        ->assertSee('Yopougon')
        ->assertDontSee('Gare d\'une autre compagnie')
        ->call('edit', 'YOP')
        ->set('place', 'Gare Siporex')
        ->set('latitude', '5.34')
        ->set('longitude', '-4.08')
        ->set('buses.standard', '6')
        ->set('buses.premium', '2')
        ->set('buses.gold', '0')
        ->call('save')
        ->assertHasNoErrors()
        ->set('seats.premium', '40')
        ->call('saveSeats')
        ->assertHasNoErrors();

    $line = CompanyLine::query()->where('company_id', $this->company->id)->sole();

    expect($line->commune_code)->toBe('YOP')
        ->and($line->place)->toBe('Gare Siporex')
        ->and($line->busesFor(Offer::Standard))->toBe(6)
        ->and($line->busesFor(Offer::Premium))->toBe(2)
        ->and($this->company->fresh()->seatsPerBus(Offer::Premium))->toBe(40)
        ->and($this->company->fresh()->seatsPerBus(Offer::Standard))->toBe(50);
});

it('refuse une commune hors des 13 communes et la saisie sans droit de gestion', function () {
    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $this->company->id]));
    Livewire::test(DefaultLines::class)->call('edit', 'XXX')->assertNotFound();

    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => $this->company->id]));
    Livewire::test(DefaultLines::class)->assertForbidden();
});

it('reprend les lignes de la compagnie comme points d\'embarquement, avec des horaires valides', function () {
    CompanyLine::factory()->for($this->company)->create(['commune_code' => 'YOP', 'place' => 'Gare Siporex']);
    CompanyLine::factory()->for($this->company)->create(['commune_code' => 'ABO', 'place' => 'Gare d\'Abobo', 'location' => Point::makeGeodetic(5.42, -4.02)]);
    $this->actingAs(userWithRole(Role::Operator));

    Livewire::test(BoardingPointsStep::class, ['event' => $this->event])
        ->assertSee('Reprendre les lignes de '.$this->company->name)
        ->call('importDefaultLines')
        ->call('importDefaultLines');

    $points = $this->event->boardingPoints()->orderBy('commune_code')->get();

    expect($points->pluck('commune_code')->all())->toBe(['ABO', 'YOP'])
        ->and($points->firstWhere('commune_code', 'YOP')->place)->toBe('Gare Siporex');

    foreach ($points as $point) {
        expect($point->departure_at->lt($this->event->starts_at))->toBeTrue()
            ->and($point->return_departure_at->gte($this->event->ends_at))->toBeTrue()
            ->and($point->departure_at->minute % 15)->toBe(0);
    }
});

it('pré-remplit le lieu habituel quand l\'admin choisit une commune desservie', function () {
    CompanyLine::factory()->for($this->company)->create(['commune_code' => 'YOP', 'place' => 'Gare Siporex', 'location' => Point::makeGeodetic(5.34, -4.08)]);
    $this->actingAs(userWithRole(Role::Operator));

    Livewire::test(BoardingPointsStep::class, ['event' => $this->event])
        ->call('create')
        ->set('commune_code', 'YOP')
        ->assertSet('place', 'Gare Siporex')
        ->assertSet('latitude', '5.34')
        ->set('commune_code', 'COC')
        ->assertSet('place', 'Gare Siporex');
});

it('coche d\'office l\'acceptation et la capacité, puis valide l\'événement en « accepté »', function () {
    $this->company->update(['seats_per_bus' => ['standard' => 60, 'premium' => 40, 'gold' => 30]]);
    CompanyLine::factory()->for($this->company)->create(['commune_code' => 'YOP', 'bus_counts' => ['standard' => 4, 'premium' => 1, 'gold' => 0]]);
    CompanyLine::factory()->for($this->company)->create(['commune_code' => 'ABO', 'bus_counts' => ['standard' => 3, 'premium' => 0, 'gold' => 0]]);
    CompanyLine::factory()->for($this->company)->create(['commune_code' => 'COC', 'bus_counts' => ['standard' => 9, 'premium' => 9, 'gold' => 0]]);
    prepareEventWithoutPoints($this->event, [Offer::Standard, Offer::Premium]);
    $this->actingAs(userWithRole(Role::Operator));
    Livewire::test(BoardingPointsStep::class, ['event' => $this->event])->call('importDefaultLines');
    $this->event->boardingPoints()->where('commune_code', 'COC')->delete();

    $checks = app(EventReadiness::class)->checks($this->event->fresh());
    expect($checks['acceptance']['ok'])->toBeTrue()
        ->and($checks['capacity']['ok'])->toBeTrue();

    Livewire::test(StatusPanel::class, ['event' => $this->event->fresh(), 'step' => 4])
        ->assertSee('Valider l\'événement', false)
        ->call('propose')
        ->assertHasNoErrors();

    $event = $this->event->fresh(['offers', 'negotiations']);
    $standard = $event->offers->firstWhere('offer', Offer::Standard);
    $premium = $event->offers->firstWhere('offer', Offer::Premium);

    expect($event->status)->toBe(EventStatus::Accepted)
        ->and($event->accepted_at)->not->toBeNull()
        ->and([$standard->bus_count, $standard->seats_per_bus])->toBe([7, 60])
        ->and([$premium->bus_count, $premium->seats_per_bus])->toBe([1, 40])
        ->and($event->negotiations->sole()->subject)->toBe(NegotiationSubject::Acceptance)
        ->and($event->negotiations->sole()->message)->toContain('Accepté d\'office');
});

it('signale une offre sans bus dans les lignes des communes de l\'événement', function () {
    CompanyLine::factory()->for($this->company)->create(['commune_code' => 'YOP', 'bus_counts' => ['standard' => 4, 'premium' => 0, 'gold' => 0]]);
    prepareEventWithoutPoints($this->event, [Offer::Standard, Offer::Gold]);
    app(CompanyDefaultLines::class)->importBoardingPoints($this->event);

    $capacity = app(EventReadiness::class)->checks($this->event->fresh())['capacity'];

    expect($capacity['ok'])->toBeFalse()
        ->and($capacity['problems'])->toBe(['Gold : aucun bus dans les lignes par défaut de la compagnie pour les communes de l\'événement.']);
});

it('garde la proposition et la négociation pour une compagnie sans lignes par défaut', function () {
    prepareEventWithoutPoints($this->event);
    $this->event->boardingPoints()->create([
        'commune' => 'Yopougon', 'commune_code' => 'YOP', 'place' => 'Gare Siporex', 'location' => Point::makeGeodetic(5.34, -4.08),
        'distance_km' => 16, 'zone' => 2, 'departure_at' => '2026-12-15 15:30', 'return_departure_at' => '2026-12-15 23:30',
    ]);
    $this->actingAs(userWithRole(Role::Operator));

    expect(app(EventReadiness::class)->checks($this->event->fresh())['acceptance']['ok'])->toBeFalse();

    Livewire::test(StatusPanel::class, ['event' => $this->event->fresh(), 'step' => 4])
        ->assertSee('Proposer à la compagnie')
        ->call('propose');

    expect($this->event->fresh()->status)->toBe(EventStatus::Proposed);
});
