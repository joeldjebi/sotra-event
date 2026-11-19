<?php

use App\Enums\AlertStatus;
use App\Enums\BreakdownType;
use App\Enums\BreakdownUrgency;
use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\EventStatus;
use App\Enums\PassType;
use App\Enums\RefundReason;
use App\Enums\Role;
use App\Livewire\Promoter\EventDetail;
use App\Livewire\Promoter\EventsIndex;
use App\Models\BreakdownAlert;
use App\Models\Complaint;
use App\Models\Event;
use App\Models\Promoter;
use App\Models\Review;
use App\Services\Reporting\PromoterEventInsights;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
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

it('montre les départs, les pannes, les avis et les réclamations sans identité des spectateurs (lot B)', function () {
    $missions = staffedMissions($this->event);
    [$bus] = outboundMissions($this->event);
    $pass = $this->order->passes->first();
    boardMission($bus, [$pass]);
    departAfter($bus, 12);

    BreakdownAlert::query()->create([
        'event_id' => $this->event->id, 'company_id' => $this->event->company_id, 'mission_id' => $bus->id, 'bus_id' => $bus->bus_id,
        'reported_by' => $bus->conveyor_id, 'type' => BreakdownType::Tyre, 'passengers_on_board' => 1, 'urgency' => BreakdownUrgency::Urgent,
        'status' => AlertStatus::Open, 'channel' => 'app', 'mission_status_before' => 'en_route', 'place_label' => 'Boulevard de la Paix',
    ]);
    Review::factory()->create([
        'pass_id' => $pass->id, 'event_id' => $this->event->id, 'company_id' => $this->event->company_id, 'mission_id' => $bus->id,
        'customer_id' => $pass->holder_id, 'punctuality' => 4, 'comfort' => 4, 'courtesy' => 4, 'driving' => 4, 'information' => 4, 'comment' => 'Bus propre et convoyeur aimable.',
    ]);
    Complaint::query()->create([
        'number' => 'RC-0001', 'event_id' => $this->event->id, 'company_id' => $this->event->company_id, 'pass_id' => $pass->id, 'customer_id' => $pass->holder_id,
        'mission_id' => $bus->id, 'category' => ComplaintCategory::Delay, 'title' => 'Départ en retard', 'details' => 'Je suis Jean, appelez-moi au 0700000000',
        'status' => ComplaintStatus::Open, 'is_collective' => false,
    ]);
    $holder = $pass->holder->phone;

    $this->actingAs($this->lead);
    $insights = app(PromoterEventInsights::class);

    expect($insights->transport($this->event))->toMatchArray(['departed' => 1, 'on_time_rate' => 0])
        ->and($insights->transport($this->event)['rows'][0])->toMatchArray(['boarded' => 1, 'gap_label' => '+12 min', 'on_time' => false])
        ->and($insights->incidents($this->event)[0])->toMatchArray(['type' => 'Pneu crevé', 'urgent' => true, 'passengers' => 1])
        ->and($insights->reviews($this->event))->toMatchArray(['rating' => 4.0, 'reviews' => 1])
        ->and($insights->complaints($this->event))->toMatchArray(['open' => 1, 'resolved' => 0]);

    $detail = fn (string $tab) => Livewire::test(EventDetail::class, ['eventId' => $this->event->id])->set('tab', $tab);

    $detail('transport')->assertSee('+12 min')->assertSee('Bus '.$bus->bus->number);
    $detail('incidents')->assertSee('Pneu crevé')->assertSee('Urgent');
    $detail('avis')->assertSee('Bus propre et convoyeur aimable.')->assertDontSee($holder);
    $detail('reclamations')->assertSee('Départ en retard')->assertDontSee('appelez-moi')->assertDontSee($holder);
    expect($missions)->not->toBeEmpty();
});

it('compte les annulations et les messages envoyés, sans montant (lot B)', function () {
    $pass = $this->order->passes->first()->fresh();
    Sanctum::actingAs($pass->holder, ['customer']);
    $this->postJson("/api/v1/passes/{$pass->code}/cancel")->assertOk();

    $this->event->promoterMessages()->create([
        'user_id' => $this->lead->id, 'audience' => 'tous', 'audience_label' => 'Tous les détenteurs de Pass', 'body' => 'Portes ouvertes à 17h.',
        'channels' => ['sms'], 'recipients_count' => 3, 'status' => 'envoye', 'sent_at' => now(),
    ]);

    expect(app(PromoterEventInsights::class)->cancellations($this->event))
        ->toMatchArray(['cancelled' => 0, 'refunded' => 1, 'by_reason' => [['label' => RefundReason::Customer->label(), 'count' => 1]]]);

    $this->actingAs($this->lead);
    Livewire::test(EventDetail::class, ['eventId' => $this->event->id])->set('tab', 'annulations')
        ->assertSee(RefundReason::Customer->label())->assertDontSee('FCFA')->assertDontSee('2 815');
    Livewire::test(EventDetail::class, ['eventId' => $this->event->id])->set('tab', 'messages')
        ->assertSee('Portes ouvertes à 17h.')->assertSee('Tous les détenteurs de Pass');
});
