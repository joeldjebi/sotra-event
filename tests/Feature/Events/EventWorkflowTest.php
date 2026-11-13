<?php

use App\Enums\DropZoneUsage;
use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\PassType;
use App\Enums\Role;
use App\Livewire\Admin\Events\StatusPanel;
use App\Livewire\Company\ProposedEvents;
use App\Livewire\Company\TransportPlan;
use App\Models\Company;
use App\Models\Event;
use App\Models\Promoter;
use App\Services\EventReadiness;
use Clickbar\Magellan\Data\Geometries\Point;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->admin = userWithRole(Role::Operator);
    $promoter = Promoter::factory()->create();
    $lead = userWithRole(Role::TransportLead, ['promoter_id' => $promoter->id]);

    $this->event = Event::factory()->create([
        'company_id' => $this->company->id,
        'promoter_id' => $promoter->id,
        'transport_lead_id' => $lead->id,
        'starts_at' => '2026-11-15 18:00',
        'ends_at' => '2026-11-15 23:00',
    ]);
});

/**
 * Complète points, grille et publication pour qu'il ne manque que l'acceptation et la capacité.
 */
function completeEvent(Event $event): void
{
    $event->boardingPoints()->create([
        'commune' => 'Yopougon', 'commune_code' => 'YOP', 'place' => 'Gare Siporex', 'location' => Point::makeGeodetic(5.34, -4.08),
        'distance_km' => 16, 'zone' => 2, 'departure_at' => '2026-11-15 15:30', 'return_departure_at' => '2026-11-15 23:30',
    ]);
    $event->offers()->create(['offer' => Offer::Standard]);
    $event->prices()->create(['zone' => 2, 'offer' => Offer::Standard, 'pass_type' => PassType::RoundTrip, 'price' => 3500]);
    $event->dropZones()->createMany([
        ['name' => 'P3', 'usage' => DropZoneUsage::DropOff, 'location' => Point::makeGeodetic(5.49, -4.07)],
        ['name' => 'P5', 'usage' => DropZoneUsage::PickUp, 'location' => Point::makeGeodetic(5.49, -4.07)],
    ]);
}

it('ne propose pas un événement incomplet', function () {
    $this->actingAs($this->admin);

    Livewire::test(StatusPanel::class, ['event' => $this->event])
        ->call('propose')
        ->assertHasErrors('event');

    expect($this->event->fresh()->status)->toBe(EventStatus::Draft);
});

it('mène un événement de la proposition à l\'ouverture de la vente (P1)', function () {
    completeEvent($this->event);

    // 1. ACCES EVENT propose
    $this->actingAs($this->admin);
    Livewire::test(StatusPanel::class, ['event' => $this->event])->set('proposalMessage', 'Pour validation')->call('propose')->assertHasNoErrors();
    expect($this->event->fresh()->status)->toBe(EventStatus::Proposed);

    // 2. La compagnie ouvre une négociation : l'événement redevient modifiable
    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => $this->company->id]));
    Livewire::test(ProposedEvents::class)
        ->call('show', $this->event->id)
        ->set('subject', 'tarifs')
        ->set('message', 'Nous proposons 4 000 FCFA en zone 2.')
        ->call('negotiate')
        ->assertHasNoErrors();
    expect($this->event->fresh()->status)->toBe(EventStatus::Negotiating)
        ->and($this->event->fresh()->status->isEditable())->toBeTrue();

    // 3. ACCES EVENT renvoie la proposition, la compagnie accepte
    $this->actingAs($this->admin);
    Livewire::test(StatusPanel::class, ['event' => $this->event->fresh()])->call('propose');
    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => $this->company->id]));
    Livewire::test(ProposedEvents::class)->call('accept', $this->event->id);
    expect($this->event->fresh()->status)->toBe(EventStatus::Accepted);

    // 4. Vente impossible tant que la capacité n'est pas définie (AD-05, RG-08)
    $this->actingAs($this->admin);
    expect(app(EventReadiness::class)->canOpenSale($this->event->fresh()))->toBeFalse();
    Livewire::test(StatusPanel::class, ['event' => $this->event->fresh()])->call('openSale')->assertHasErrors('event');

    // 5. L'exploitation fixe bus et places par offre
    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $this->company->id]));
    $offerId = $this->event->offers()->sole()->id;
    Livewire::test(TransportPlan::class)
        ->set("capacity.{$offerId}.bus_count", '60')
        ->set("capacity.{$offerId}.seats_per_bus", '50')
        ->call('save', $this->event->id)
        ->assertHasNoErrors();
    expect($this->event->offers()->sole()->capacity())->toBe(3000);

    // 6. Ouverture de la vente
    $this->actingAs($this->admin);
    Livewire::test(StatusPanel::class, ['event' => $this->event->fresh()])->call('openSale')->assertHasNoErrors();

    $event = $this->event->fresh();
    expect($event->status)->toBe(EventStatus::OnSale)
        ->and($event->sale_opened_at)->not->toBeNull()
        ->and($event->negotiations()->count())->toBe(4);
});

it('exige les zones de dépose et de reprise avant l\'ouverture (AD-04)', function () {
    completeEvent($this->event);
    $this->event->dropZones()->where('usage', DropZoneUsage::PickUp)->delete();

    $checks = app(EventReadiness::class)->checks($this->event->fresh());

    expect($checks['publication']['ok'])->toBeFalse()
        ->and($checks['publication']['problems'][0])->toContain('Reprise retour');
});

it('ne montre pas les brouillons à la compagnie', function () {
    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => $this->company->id]));

    Livewire::test(ProposedEvents::class)->assertDontSee($this->event->name);
    Livewire::test(ProposedEvents::class)->call('accept', $this->event->id)->assertNotFound();
});

it('empêche une compagnie d\'agir sur l\'événement d\'une autre', function () {
    $this->event->update(['status' => EventStatus::Proposed]);
    $this->actingAs(userWithRole(Role::Commercial, ['company_id' => Company::factory()->create()->id]));

    Livewire::test(ProposedEvents::class)->call('accept', $this->event->id)->assertNotFound();
    expect($this->event->fresh()->status)->toBe(EventStatus::Proposed);
});

it('réserve l\'acceptation à la direction commerciale', function () {
    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $this->company->id]));

    Livewire::test(ProposedEvents::class)->assertForbidden();
});

it('ne laisse pas un promoteur voir les événements d\'un autre', function () {
    $other = Event::factory()->create();
    $this->actingAs(userWithRole(Role::TransportLead, ['promoter_id' => $this->event->promoter_id]));

    expect(Event::query()->pluck('id')->all())->toBe([$this->event->id])
        ->and(Event::query()->find($other->id))->toBeNull();
});
