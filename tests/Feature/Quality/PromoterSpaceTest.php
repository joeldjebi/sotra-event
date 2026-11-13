<?php

use App\Enums\MessageAudience;
use App\Enums\Role;
use App\Livewire\Promoter\DropZones;
use App\Livewire\Promoter\EventSheet;
use App\Livewire\Promoter\Messages;
use App\Livewire\Promoter\Report;
use App\Livewire\Promoter\Tracking;
use App\Models\DropZone;
use App\Models\OutboundMessage;
use App\Models\Promoter;
use App\Models\PromoterMessage;
use App\Models\SmsMessage;
use App\Services\Reporting\ArrivalTracking;
use Livewire\Livewire;

beforeEach(function () {
    $this->event = eventOnSale(seats: 1);
    $this->event->offers()->update(['bus_count' => 5]);
    $this->order = paidOrder($this->event, 2);
    staffedMissions($this->event);
    [$this->busA, $this->busB] = outboundMissions($this->event);
    $this->lead = userWithRole(Role::TransportLead, ['promoter_id' => $this->event->promoter_id]);
});

it('suit les arrivées : spectateurs arrivés, bus, retards et flux prévu / réel (PR-02, PR-03)', function () {
    boardMission($this->busA, [$this->order->passes->first()]);
    departAfter($this->busA, 0);
    $this->travelTo($this->busA->planned_departure_at->copy()->addMinutes(33));
    $this->postJson("/api/v1/missions/{$this->busA->id}/arrive")->assertOk();

    $tracking = app()->make(ArrivalTracking::class, ['event' => $this->event]);
    expect($tracking->summary())->toMatchArray(['arrived' => 1, 'sold' => 2, 'buses_arrived' => 1, 'buses_planned' => 2])
        ->and($tracking->summary()['delays'])->toHaveCount(1)
        ->and($tracking->flow()['labels'])->toBe(['16h15'])
        ->and($tracking->flow()['series'][0]['values'])->toBe([2])
        ->and($tracking->flow()['series'][1]['values'])->toBe([1]);

    $this->actingAs($this->lead);
    Livewire::test(Tracking::class)
        ->assertSee($this->event->name)
        ->assertSee('sur 2 Pass vendus')
        ->assertSee('1 / 2')
        ->assertSee('Départ attendu depuis 33 min');
});

it('envoie un message ciblé aux détenteurs de Pass et le journalise, dans la limite du plafond (PR-04, PR-08)', function () {
    config(['eventtrans.promoter_messages.max_per_event' => 1]);
    $this->actingAs($this->lead);

    Livewire::test(Messages::class)
        ->set('audience', MessageAudience::Bus->value)
        ->set('audienceValue', (string) $this->busA->id)
        ->assertSee('1 spectateur(s) recevront ce message.')
        ->set('body', 'Ouverture des portes à 18 h 30.')
        ->set('channels', ['sms', 'push'])
        ->call('sendMessage')
        ->assertHasNoErrors()
        ->assertSee('Ouverture des portes à 18 h 30.')
        ->assertSee('0 envoi(s) restant(s)')
        ->set('body', 'Deuxième message')
        ->call('sendMessage')
        ->assertHasErrors('body');

    expect(PromoterMessage::query()->sole())->recipients_count->toBe(1)->audience_label->toStartWith('Bus 20')
        ->and(SmsMessage::query()->where('body', 'like', '%'.$this->event->name.' : Ouverture des portes%')->count())->toBe(1)
        ->and(OutboundMessage::query()->where('channel', 'whatsapp')->where('template', 'promoter_message')->exists())->toBeFalse();
});

it('gère les zones de dépose et affiche la fiche événement (PR-05, PR-07)', function () {
    $this->actingAs($this->lead);

    Livewire::test(DropZones::class)
        ->call('createZone')
        ->set('zone_name', 'Parking P3 · porte Nord')
        ->set('zone_usage', 'depose')
        ->set('zone_latitude', '5.4930')
        ->set('zone_longitude', '-4.0800')
        ->set('zone_radius', '200')
        ->call('saveZone')
        ->assertHasNoErrors()
        ->assertSee('Parking P3 · porte Nord');

    expect(DropZone::query()->where('event_id', $this->event->id)->where('name', 'Parking P3 · porte Nord')->exists())->toBeTrue();

    Livewire::test(EventSheet::class)->assertSee('Gare Siporex')->assertSee('15h45');
});

it('produit le bilan et son PDF, et cloisonne les événements par promoteur (PR-09)', function () {
    $this->actingAs($this->lead);
    Livewire::test(Report::class)->assertSee('Transportés aller')->assertSee('Exporter le bilan (PDF)');
    $this->get("/promoteur/bilan/{$this->event->id}.pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

    $stranger = userWithRole(Role::TransportLead, ['promoter_id' => Promoter::factory()->create()->id]);
    $this->actingAs($stranger)->get("/promoteur/bilan/{$this->event->id}.pdf")->assertNotFound();
    Livewire::actingAs($stranger)->test(Tracking::class)->assertDontSee($this->event->name)->assertSee('Aucun événement en cours');
});
