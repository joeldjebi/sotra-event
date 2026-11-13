<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Site\ChoosePass;
use App\Livewire\Site\Login;
use App\Livewire\Site\PassShow;
use App\Livewire\Site\PayOrder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SmsMessage;
use Livewire\Livewire;

beforeEach(fn () => $this->event = eventOnSale(seats: 10, attributes: ['featured' => true, 'name' => 'Concert au stade d\'Ebimpé']));

it('affiche les événements en vente sur l\'accueil (SP-01 à SP-03)', function () {
    $this->get('/')->assertOk()->assertSee('Où allez-vous ?')->assertSee('Concert au stade d\'Ebimpé')->assertSee('Pass dès');
});

it('connecte un spectateur par SMS et crée son compte', function () {
    Livewire::test(Login::class)
        ->set('phone', '07 07 08 08 39')
        ->call('sendCode')
        ->assertSet('codeSent', true);

    preg_match('/\b(\d{6})\b/', SmsMessage::query()->latest('id')->value('body'), $code);

    Livewire::test(Login::class)
        ->set('phone', '0707080839')
        ->set('codeSent', true)
        ->set('code', $code[1])
        ->call('verify')
        ->assertRedirect(route('site.passes.index'));

    expect(auth('customer')->user()?->phone)->toBe('+2250707080839');
});

it('envoie un visiteur se connecter en gardant sa sélection', function () {
    Livewire::test(ChoosePass::class, ['slug' => $this->event->slug])
        ->set('quantity', 2)
        ->call('pay')
        ->assertRedirect(route('site.login'));

    expect(session('url.intended'))->toContain('nb=2');
});

it('achète un Pass sur le site, paie et l\'affiche (P2 sur le web)', function () {
    $customer = Customer::factory()->create(['phone' => '+2250707080839']);
    $this->actingAs($customer, 'customer');

    $component = Livewire::test(ChoosePass::class, ['slug' => $this->event->slug])
        ->assertSee('3 000 FCFA')
        ->call('increment')
        ->assertSee('6 120 FCFA')
        ->call('pay')
        ->assertHasErrors('acceptTerms')
        ->set('acceptTerms', true)
        ->call('pay');

    $order = Order::query()->sole();
    $component->assertRedirect(route('site.orders.pay', $order));
    expect($order->channel->value)->toBe('web');

    $pay = Livewire::test(PayOrder::class, ['order' => $order])
        ->set('method', 'orange_money')
        ->call('pay')
        ->assertSee('En attente de confirmation');

    $payment = Payment::query()->sole();
    $this->post(route('payments.simulator.respond', $payment->provider_reference), ['outcome' => 'accept'])->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);

    $firstPass = $order->passes()->orderBy('id')->first();
    $pay->call('checkStatus')->assertRedirect(route('site.passes.show', $firstPass));

    Livewire::test(PassShow::class, ['pass' => $firstPass])
        ->assertSee($firstPass->code)
        ->assertSee('Présentez ce code au convoyeur')
        ->assertSeeHtml('<svg');
});

it('n\'affiche pas le Pass ou la commande d\'un autre spectateur', function () {
    $owner = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $owner->id, 'event_id' => $this->event->id, 'boarding_point_id' => $this->event->boardingPoints->first()->id]);

    $this->actingAs(Customer::factory()->create(), 'customer');

    $this->get(route('site.orders.pay', $order))->assertNotFound();
});

it('redirige vers la connexion spectateur pour Mes Pass', function () {
    $this->get('/mes-pass')->assertRedirect(route('site.login'));
});

it('donne au promoteur le lien de vente et le QR (PR-06)', function () {
    $lead = userWithRole(Role::TransportLead, ['promoter_id' => $this->event->promoter_id]);

    $this->actingAs($lead)->get(route('promoter.kit'))
        ->assertOk()
        ->assertSee(route('site.events.show', $this->event->slug));

    $this->get(route('promoter.kit.qr', $this->event))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');
});
