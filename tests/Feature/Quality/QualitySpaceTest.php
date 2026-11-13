<?php

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\Role;
use App\Livewire\Company\Quality\Complaints;
use App\Livewire\Company\Quality\Crews;
use App\Livewire\Company\Quality\Overview;
use App\Livewire\Company\Quality\PilotReview;
use App\Livewire\Company\Quality\Punctuality;
use App\Livewire\Company\Quality\Reviews;
use App\Models\Company;
use App\Models\Complaint;
use App\Models\SmsMessage;
use App\Services\Quality\ComplaintService;
use App\Services\Quality\ReviewService;
use App\Services\Reporting\QualityMetrics;
use App\Services\Sales\PassTokenService;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    // Deux bus d'une place : chaque spectateur a son bus aller.
    $this->event = eventOnSale(seats: 1);
    $this->event->offers()->update(['bus_count' => 5]);
    $this->order = paidOrder($this->event, 2);
    staffedMissions($this->event);
    [$this->busA, $this->busB] = outboundMissions($this->event);
    [$this->first, $this->second] = $this->order->passes->map->fresh()->all();

    boardMission($this->busA, [$this->first]);
    boardMission($this->busB, [$this->second]);
    departAfter($this->busA, 10);
    departAfter($this->busB, 11);

    app(ReviewService::class)->submit($this->first->fresh(), ['punctuality' => 5, 'comfort' => 5, 'courtesy' => 5, 'driving' => 5, 'information' => 5]);
    app(ReviewService::class)->submit($this->second->fresh(), ['punctuality' => 3, 'comfort' => 3, 'courtesy' => 3, 'driving' => 3, 'information' => 3], 'Un peu en retard');

    // Un Pass présenté une deuxième fois : refusé (rouge).
    Sanctum::actingAs($this->busA->conveyor()->first(), ['conveyor']);
    $this->postJson('/api/v1/scans', ['mission_id' => $this->busA->id, 'input' => app(PassTokenService::class)->sign($this->first->fresh()), 'client_uuid' => (string) Str::uuid()])
        ->assertJsonPath('data.result', 'rouge');

    $this->quality = userWithRole(Role::Quality, ['company_id' => $this->event->company_id]);
});

it('calcule les indicateurs du CDC : à l\'heure jusqu\'à +10 min, notes, Pass scannés et refusés (QU-01, QU-02)', function () {
    $metrics = new QualityMetrics($this->event);

    expect($metrics->summary())->toMatchArray([
        'rating' => 4.0,
        'reviews' => 2,
        'response_rate' => 100,
        'departed' => 2,
        'on_time' => 1,
        'on_time_rate' => 50,
        'sold_outbound' => 2,
        'scanned_outbound' => 2,
        'refused' => 1,
    ])->and($metrics->criteria()['driving'])->toBe(['label' => 'Conduite du chauffeur', 'average' => 4.0]);

    $crews = collect($metrics->crews())->keyBy(fn (array $crew) => $crew['mission']->id);
    expect($crews[$this->busA->id])->toMatchArray(['gap_label' => '+10 min', 'on_time' => true, 'driver_rating' => 5.0, 'reviews' => 1])
        ->and($crews[$this->busB->id])->toMatchArray(['gap_label' => '+11 min', 'on_time' => false, 'conveyor_rating' => 3.0]);
});

it('affiche les écrans de la Direction qualité', function () {
    $this->actingAs($this->quality);

    Livewire::test(Overview::class)->assertSee('4,0 / 5')->assertSee('50 %')->assertSee('Générer le rapport PDF');
    Livewire::test(Reviews::class)->assertSee('Un peu en retard')->set('filter', 'commentaires')->assertSee('3,0 ★')->assertDontSee('5,0 ★')->set('filter', 'faibles')->assertSee('Aucun avis');
    Livewire::test(Punctuality::class)->assertSee('+11 min')->set('direction', 'retour')->assertSee('non parti');
    Livewire::test(Crews::class)->assertSee('Bus 20')->assertSee('+10 min');
    Livewire::test(PilotReview::class)->assertSee($this->event->name)->assertSee('50 %');
});

it('traite une réclamation : en cours puis traitée, avec la réponse envoyée par SMS (QU-03)', function () {
    $complaint = app(ComplaintService::class)->open($this->first->fresh(), ComplaintCategory::Comfort, 'Sièges sales');
    $this->actingAs($this->quality);

    Livewire::test(Complaints::class)
        ->assertSee('1 ouverte(s)')
        ->call('handle', $complaint->id)
        ->assertSee('Sièges sales')
        ->call('resolve')
        ->assertHasErrors('resolution')
        ->set('resolution', 'Le bus a été nettoyé, merci du signalement.')
        ->call('resolve')
        ->assertHasNoErrors()
        ->assertSee('1 traitée(s)');

    expect($complaint->fresh())
        ->status->toBe(ComplaintStatus::Resolved)
        ->handled_by->toBe($this->quality->id)
        ->and(SmsMessage::query()->where('body', 'like', '%'.$complaint->number.'%nettoyé%')->exists())->toBeTrue();
});

it('génère le rapport qualité PDF, cloisonné à la compagnie (QU-05)', function () {
    $this->actingAs($this->quality)
        ->get("/compagnie/qualite/rapport/{$this->event->id}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $other = userWithRole(Role::Quality, ['company_id' => Company::factory()->create()->id]);
    $this->actingAs($other)->get("/compagnie/qualite/rapport/{$this->event->id}")->assertNotFound();
});

it('réserve les écrans qualité aux profils autorisés', function () {
    $commercial = userWithRole(Role::Commercial, ['company_id' => $this->event->company_id]);

    $this->actingAs($commercial)->get('/compagnie/qualite')->assertForbidden();
    $this->actingAs($commercial)->get('/compagnie/qualite/reclamations')->assertForbidden();
    $this->actingAs($this->quality)->get('/compagnie/qualite/reclamations')->assertOk();
    expect(Complaint::query()->count())->toBe(0);
});
