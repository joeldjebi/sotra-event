<?php

use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\PaymentMethod;
use App\Enums\RefundMode;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Enums\Role;
use App\Livewire\Admin\Events\Disruption;
use App\Livewire\Admin\PassesTable;
use App\Livewire\Admin\RefundsTable;
use App\Livewire\Site\PassShow;
use App\Models\Mission;
use App\Models\OutboundMessage;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SmsMessage;
use App\Services\Operations\MissionPlanner;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-11-10 10:00');
    $this->event = eventOnSale(seats: 10);
    $this->order = paidOrder($this->event, 2);
    $this->pass = $this->order->passes->first()->fresh();
});

it('annule à la demande du spectateur : 3 060 payés, 245 retenus, 2 815 remboursés par API (RG-19)', function () {
    Sanctum::actingAs($this->pass->holder, ['customer']);

    $this->getJson("/api/v1/passes/{$this->pass->code}")
        ->assertJsonPath('data.cancellation.allowed', true)
        ->assertJsonPath('data.cancellation.paid', 3060)
        ->assertJsonPath('data.cancellation.retained', 245)
        ->assertJsonPath('data.cancellation.refund_amount', 2815);

    $this->postJson("/api/v1/passes/{$this->pass->code}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'rembourse')
        ->assertJsonPath('data.refund.amount', 2815)
        ->assertJsonPath('data.refund.retained', 245);

    expect(Refund::query()->sole())
        ->reason->toBe(RefundReason::Customer)
        ->mode->toBe(RefundMode::Api)
        ->status->toBe(RefundStatus::Refunded)
        ->provider_reference->toStartWith('FAKE-RF-')
        ->and(SmsMessage::query()->where('body', 'like', '%remboursé : 2 815 FCFA sur votre Wave (245 FCFA de frais d\'annulation retenus)%')->exists())->toBeTrue();

    $this->postJson("/api/v1/passes/{$this->pass->code}/cancel")->assertStatus(409)->assertJsonPath('code', 'PASS_NOT_CANCELLABLE');
});

it('refuse l\'annulation à moins de 24 h du départ ou après le scan', function () {
    Sanctum::actingAs($this->pass->holder, ['customer']);
    $this->travelTo($this->event->boardingPoints->first()->departure_at->copy()->subHours(23));

    $this->getJson("/api/v1/passes/{$this->pass->code}")->assertJsonPath('data.cancellation.allowed', false)
        ->assertJsonPath('data.cancellation.message', 'L\'annulation n\'est plus possible à moins de 24 h du départ.');
    $this->postJson("/api/v1/passes/{$this->pass->code}/cancel")->assertStatus(409);

    $this->pass->update(['status' => PassStatus::OutboundUsed]);
    $this->travelBack();
    $this->travelTo('2026-11-10 10:00');
    $this->postJson("/api/v1/passes/{$this->pass->code}/cancel")->assertStatus(409)->assertJsonPath('message', 'Un Pass déjà scanné ne peut plus être annulé.');
    expect(Refund::query()->count())->toBe(0);
});

it('libère la place et invalide le QR du Pass annulé', function () {
    $this->event->offers()->update(['seats_per_bus' => 2]);
    Sanctum::actingAs($this->pass->holder, ['customer']);
    $this->postJson('/api/v1/orders', ['event_id' => $this->event->id, 'boarding_point_id' => $this->event->boardingPoints->first()->id, 'offer' => 'standard', 'pass_type' => 'aller_retour', 'quantity' => 1, 'accept_terms' => true])
        ->assertStatus(409);

    $this->postJson("/api/v1/passes/{$this->pass->code}/cancel")->assertOk();
    $this->postJson('/api/v1/orders', ['event_id' => $this->event->id, 'boarding_point_id' => $this->event->boardingPoints->first()->id, 'offer' => 'standard', 'pass_type' => 'aller_retour', 'quantity' => 1, 'accept_terms' => true])
        ->assertCreated();
});

it('passe en traitement manuel quand l\'opérateur ne rembourse pas par API, puis le support le clôt (PA-04)', function () {
    Payment::query()->where('order_id', $this->order->id)->update(['method' => PaymentMethod::MoovMoney]);
    $this->actingAs($this->pass->holder, 'customer');

    Livewire::test(PassShow::class, ['pass' => $this->pass])
        ->assertSee('Annuler mon Pass et être remboursé')
        ->call('cancelPass')
        ->assertSee('Remboursement de 2 815 FCFA en cours');

    $refund = Refund::query()->sole();
    expect($refund)->mode->toBe(RefundMode::Manual)->status->toBe(RefundStatus::Pending)
        ->and($this->pass->fresh()->status)->toBe(PassStatus::Cancelled);

    $this->actingAs(userWithRole(Role::Operator));
    Livewire::test(RefundsTable::class)
        ->assertSee($refund->reference)
        ->call('openManual', $refund->id)
        ->set('operationReference', 'MOOV-2026-1234')
        ->call('markRefunded')
        ->assertHasNoErrors();

    expect($refund->fresh())->status->toBe(RefundStatus::Refunded)->provider_reference->toBe('MOOV-2026-1234')
        ->and($this->pass->fresh()->status)->toBe(PassStatus::Refunded);
});

it('rembourse intégralement par geste commercial depuis Pass & ventes', function () {
    $this->actingAs(userWithRole(Role::SuperAdmin));

    Livewire::test(PassesTable::class)
        ->call('show', $this->pass->id)
        ->set('cancelReason', 'geste_commercial')
        ->set('cancelNote', 'Spectateur hospitalisé')
        ->call('cancelPass')
        ->assertHasNoErrors();

    expect(Refund::query()->sole())->amount->toBe(3060)->retained_amount->toBe(0)->note->toBe('Spectateur hospitalisé');
});

it('reporte l\'événement : horaires décalés, détenteurs prévenus, remboursement sans frais sur demande (RG-18)', function () {
    app(MissionPlanner::class)->plan($this->event);
    $mission = Mission::query()->where('event_id', $this->event->id)->where('direction', 'aller')->sole();
    $this->actingAs(userWithRole(Role::SuperAdmin));

    Livewire::test(Disruption::class, ['event' => $this->event])
        ->set('newDate', '2026-11-22')
        ->set('newTime', '19:00')
        ->call('postpone')
        ->assertSee('Reporté le');

    $event = $this->event->fresh(['boardingPoints']);
    expect($event->starts_at->format('Y-m-d H:i'))->toBe('2026-11-22 19:00')
        ->and($event->original_starts_at->format('Y-m-d H:i'))->toBe('2026-11-15 18:00')
        ->and($event->boardingPoints->first()->departure_at->format('Y-m-d H:i'))->toBe('2026-11-22 16:45')
        ->and($mission->fresh()->planned_departure_at->format('Y-m-d H:i'))->toBe('2026-11-22 16:45')
        ->and(OutboundMessage::query()->where('template', 'event_postponed')->count())->toBe(1);

    Sanctum::actingAs($this->pass->holder, ['customer']);
    $this->postJson("/api/v1/passes/{$this->pass->code}/cancel")->assertOk()->assertJsonPath('data.refund.amount', 3060);
    expect(Refund::query()->sole()->reason)->toBe(RefundReason::EventPostponed);
});

it('annule l\'événement : tous les Pass remboursés intégralement, frais compris (RG-17, AD-13)', function () {
    paidOrder($this->event, 1);
    $this->actingAs(userWithRole(Role::SuperAdmin));

    Livewire::test(Disruption::class, ['event' => $this->event])
        ->call('cancelEvent')
        ->assertHasErrors('cancelReason')
        ->set('cancelReason', 'Décision des autorités')
        ->call('cancelEvent')
        ->assertSee('Aucune action possible');

    expect($this->event->fresh()->status)->toBe(EventStatus::Cancelled)
        ->and(Refund::query()->count())->toBe(3)
        ->and(Refund::query()->sum('amount'))->toBe(3 * 3060)
        ->and(Refund::query()->where('status', RefundStatus::Refunded)->count())->toBe(3)
        ->and(OutboundMessage::query()->where('template', 'event_cancelled')->count())->toBe(2);
});

it('annule un départ : ses Pass remboursés et la vente fermée sur ce point', function () {
    $point = $this->event->boardingPoints->first();
    $this->actingAs(userWithRole(Role::SuperAdmin));

    Livewire::test(Disruption::class, ['event' => $this->event])
        ->set('departureId', $point->id)
        ->set('departureReason', 'Route impraticable')
        ->call('cancelDeparture')
        ->assertSee('Yopougon annulé');

    expect(Refund::query()->where('reason', RefundReason::DepartureCancelled)->count())->toBe(2);

    Sanctum::actingAs($this->pass->holder, ['customer']);
    $this->postJson('/api/v1/orders', ['event_id' => $this->event->id, 'boarding_point_id' => $point->id, 'offer' => Offer::Standard->value, 'pass_type' => PassType::RoundTrip->value, 'quantity' => 1, 'accept_terms' => true])
        ->assertStatus(409)->assertJsonPath('code', 'DEPARTURE_CANCELLED');
});
