<?php

use App\Enums\EventStatus;
use App\Enums\PassType;
use App\Enums\Role;
use App\Livewire\Promoter\EventDetail;
use App\Livewire\Promoter\EventsIndex;
use App\Models\Event;
use App\Models\Promoter;
use App\Services\Reporting\PromoterEventInsights;
use Livewire\Livewire;

beforeEach(function () {
    $this->event = eventOnSale(seats: 10, attributes: ['sale_opened_at' => '2026-11-11 09:00']);
    $this->event->offers()->update(['bus_count' => 5]);
    $this->order = paidOrder($this->event, 3);
    paidOrder($this->event, 1, PassType::Outbound);
    $this->lead = userWithRole(Role::TransportLead, ['promoter_id' => $this->event->promoter_id]);
});

it('liste les événements du promoteur à partir de leur acceptation, avec ventes et remplissage', function () {
    Event::factory()->create(['promoter_id' => $this->event->promoter_id, 'name' => 'Brouillon caché', 'status' => EventStatus::Draft]);
    Event::factory()->create(['name' => 'Événement d\'un autre promoteur', 'status' => EventStatus::OnSale]);

    $this->actingAs($this->lead)->get('/promoteur/evenements')->assertOk();

    Livewire::test(EventsIndex::class)
        ->set('period', 'tous')
        ->assertSee($this->event->name)
        ->assertSee('/ 50')
        ->assertSee('8 %')
        ->assertDontSee('Brouillon caché')
        ->assertDontSee('Événement d\'un autre promoteur');
});

it('sépare les événements à venir des événements terminés', function () {
    $past = Event::factory()->create(['promoter_id' => $this->event->promoter_id, 'name' => 'Festival passé', 'status' => EventStatus::Finished, 'starts_at' => '2026-10-01 18:00', 'ends_at' => '2026-10-01 23:00']);
    $this->actingAs($this->lead);

    Livewire::test(EventsIndex::class)->assertSee($this->event->name)->assertDontSee($past->name)
        ->set('period', 'termines')->assertSee($past->name)->assertDontSee($this->event->name);
});

it('détaille les ventes en volumes, sans aucun montant', function () {
    $this->actingAs($this->lead)->get(route('promoter.events.show', $this->event))->assertOk()->assertSee($this->event->name);

    $sales = app(PromoterEventInsights::class)->sales($this->event);

    expect($sales['sold'])->toBe(4)
        ->and($sales['by_offer'][0])->toMatchArray(['passes' => 4, 'capacity' => 50, 'remaining' => 46])
        ->and(collect($sales['by_type'])->mapWithKeys(fn ($row) => [$row['type']->value => $row['passes']])->all())
        ->toBe(['aller_retour' => 3, 'aller_simple' => 1, 'retour_simple' => 0])
        ->and(array_sum($sales['daily']['series'][0]['values']))->toBe(4)
        ->and($sales['daily']['labels'])->toBe(['11/11', '12/11', '13/11']);

    Livewire::test(EventDetail::class, ['eventId' => $this->event->id])
        ->set('tab', 'ventes')
        ->assertSee('Pass vendus par jour')
        ->assertSee('Aller simple')
        ->assertDontSee('FCFA')
        ->assertDontSee('Recette');
});

it('compte les montées par point et l\'embarquement, avec les scans refusés par motif', function () {
    staffedMissions($this->event);
    [$bus] = outboundMissions($this->event);
    $pass = $this->order->passes->first();
    boardMission($bus, [$pass]);
    // Second scan du même Pass : refusé « déjà utilisé ».
    $this->postJson('/api/v1/scans', ['mission_id' => $bus->id, 'input' => $pass->fresh()->code, 'client_uuid' => (string) Str::uuid()]);

    $insights = app(PromoterEventInsights::class);
    $boarding = $insights->boarding($this->event);

    expect($boarding['outbound'])->toBe(['boarded' => 1, 'expected' => 4, 'rate' => 25])
        ->and($boarding['return'])->toBe(['boarded' => 0, 'expected' => 3, 'rate' => 0])
        ->and($boarding['refused'])->toBe(1)
        ->and($boarding['refusals'][0]['count'])->toBe(1)
        ->and($insights->points($this->event)[0])->toMatchArray(['passes' => 4, 'boarded_outbound' => 1, 'boarded_return' => 0]);

    $this->actingAs($this->lead);
    Livewire::test(EventDetail::class, ['eventId' => $this->event->id])->set('tab', 'embarquement')->assertSee('25 % des Pass utilisés');
    Livewire::test(EventDetail::class, ['eventId' => $this->event->id])->set('tab', 'points')->assertSee('Yopougon')->assertSee('Gare Siporex');
});

it('refuse l\'événement d\'un autre promoteur et un brouillon', function () {
    $other = eventOnSale(attributes: ['promoter_id' => Promoter::factory()->create()->id]);
    $draft = Event::factory()->create(['promoter_id' => $this->event->promoter_id, 'status' => EventStatus::Draft]);

    $this->actingAs($this->lead);
    $this->get(route('promoter.events.show', $other))->assertNotFound();
    $this->get(route('promoter.events.show', $draft))->assertNotFound();
});

it('affiche les étapes de l\'événement et le lien de vente', function () {
    $this->event->update(['proposed_at' => '2026-11-05 10:00', 'accepted_at' => '2026-11-06 10:00']);
    $this->actingAs($this->lead);

    Livewire::test(EventDetail::class, ['eventId' => $this->event->id])
        ->assertSee('Accepté par la compagnie')
        ->assertSee('Vente ouverte')
        ->assertSee(route('site.events.show', $this->event->slug));
});
