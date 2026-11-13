<?php

use App\Enums\ComplaintStatus;
use App\Enums\PassStatus;
use App\Enums\Role;
use App\Livewire\Site\PassShow;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Review;
use App\Models\SmsMessage;
use App\Services\Quality\ComplaintService;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->event = eventOnSale(seats: 10);
    $this->order = paidOrder($this->event, 2);
    [$this->outbound] = staffedMissions($this->event);
    $this->pass = $this->order->passes->first();
});

$ratings = ['punctuality' => 5, 'comfort' => 4, 'courtesy' => 5, 'driving' => 4, 'information' => 3];

it('n\'accepte l\'avis qu\'après le trajet, une seule fois, et le rattache au bus (SP-22)', function () use ($ratings) {
    Sanctum::actingAs($this->pass->holder, ['customer']);
    $this->postJson("/api/v1/passes/{$this->pass->code}/review", $ratings)->assertUnprocessable()->assertJsonPath('code', 'REVIEW_NOT_AVAILABLE');
    $this->getJson("/api/v1/passes/{$this->pass->code}")->assertJsonPath('data.review', ['sent' => false, 'available' => false]);

    boardMission($this->outbound, [$this->pass]);

    Sanctum::actingAs($this->pass->holder, ['customer']);
    $this->postJson("/api/v1/passes/{$this->pass->code}/review", ['punctuality' => 6] + $ratings)->assertJsonValidationErrors('punctuality');
    $this->postJson("/api/v1/passes/{$this->pass->code}/review", $ratings + ['comment' => 'Très bien'])->assertCreated()->assertJsonPath('data.average', 4.2);
    $this->postJson("/api/v1/passes/{$this->pass->code}/review", $ratings)->assertStatus(409)->assertJsonPath('code', 'REVIEW_ALREADY_SENT');

    expect(Review::query()->sole())->mission_id->toBe($this->outbound->id)->company_id->toBe($this->event->company_id);
});

it('refuse l\'avis et la réclamation sur le Pass d\'un autre spectateur', function () use ($ratings) {
    Sanctum::actingAs(Customer::factory()->create(), ['customer']);

    $this->postJson("/api/v1/passes/{$this->pass->code}/review", $ratings)->assertNotFound();
    $this->postJson("/api/v1/passes/{$this->pass->code}/complaints", ['category' => 'autre'])->assertNotFound();
});

it('enregistre une réclamation et en suit le statut jusqu\'à la réponse par SMS (SP-23)', function () {
    Sanctum::actingAs($this->pass->holder, ['customer']);

    $this->postJson("/api/v1/passes/{$this->pass->code}/complaints", ['category' => 'bus_parti', 'details' => 'Le bus retour est parti sans moi.'])
        ->assertCreated()
        ->assertJsonPath('data.number', 'RC-0001')
        ->assertJsonPath('data.status_label', 'Ouverte');

    $complaint = Complaint::query()->sole();
    // Le bus retour n'est connu qu'à la montée : la réclamation reste sans bus.
    expect($complaint->mission_id)->toBeNull();

    $this->actingAs(userWithRole(Role::Quality, ['company_id' => $this->event->company_id]));
    app(ComplaintService::class)->resolve($complaint, auth()->user(), 'Votre trajet retour vous sera remboursé.');

    Sanctum::actingAs($this->pass->holder, ['customer']);
    $this->getJson('/api/v1/me/complaints')->assertJsonPath('data.0.status', 'traitee')->assertJsonPath('data.0.resolution', 'Votre trajet retour vous sera remboursé.');
    expect(SmsMessage::query()->where('body', 'like', '%RC-0001%remboursé%')->exists())->toBeTrue();
});

it('permet l\'avis et la réclamation depuis « Mon Pass » sur le site', function () {
    boardMission($this->outbound, [$this->pass]);
    $this->actingAs($this->pass->holder, 'customer');

    Livewire::test(PassShow::class, ['pass' => $this->pass])
        ->assertSee('Donner mon avis')
        ->set('ratings', ['punctuality' => 5, 'comfort' => 5, 'courtesy' => 5, 'driving' => 5, 'information' => 4])
        ->call('sendReview')
        ->assertHasNoErrors()
        ->assertSee('Avis envoyé · 4,8/5')
        ->set('category', 'confort')
        ->set('details', 'La climatisation ne fonctionnait pas.')
        ->call('sendComplaint')
        ->assertHasNoErrors()
        ->assertSee('RC-0001 · Confort / propreté du bus');

    expect($this->pass->fresh()->status)->toBe(PassStatus::OutboundUsed)
        ->and(Complaint::query()->sole()->status)->toBe(ComplaintStatus::Open);
});

it('ouvre une réclamation collective et prévient les passagers quand un bus part avec plus de 20 min de retard', function () {
    boardMission($this->outbound, $this->order->passes);
    departAfter($this->outbound, 21);

    $complaint = Complaint::query()->sole();
    expect($complaint)
        ->is_collective->toBeTrue()
        ->affected_count->toBe(2)
        ->title->toStartWith('Retard de 21 min · Bus 20')
        ->and(SmsMessage::query()->where('body', 'like', '%21 min de retard%')->count())->toBe(1);

    // Une seule réclamation collective par départ, même si la tâche planifiée repasse.
    $this->artisan('missions:check-delays')->assertSuccessful();
    expect(Complaint::query()->count())->toBe(1);
});

it('ne crée rien à 20 min de retard, mais signale un départ jamais confirmé après 20 min', function () {
    departAfter($this->outbound, 20);
    expect(Complaint::query()->count())->toBe(0);

    $return = $this->outbound->event->missions()->where('direction', 'retour')->sole();
    $this->travelTo($return->planned_departure_at->copy()->addMinutes(25));
    $this->artisan('missions:check-delays')->expectsOutputToContain('1 réclamation(s)')->assertSuccessful();

    expect(Complaint::query()->sole()->mission_id)->toBe($return->id);
});
