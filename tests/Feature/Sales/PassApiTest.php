<?php

use App\Actions\Orders\CreateOrder;
use App\Actions\Payments\ApplyPaymentResult;
use App\Actions\Payments\InitiatePayment;
use App\Enums\Offer;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Models\Customer;
use App\Models\Pass;
use App\Services\Sales\PassNotifier;
use App\Services\Sales\PassTokenService;
use Laravel\Sanctum\Sanctum;

/**
 * Pass payé et émis, détenu par $customer.
 */
function paidPass(Customer $customer): Pass
{
    $event = eventOnSale();
    $order = app(CreateOrder::class)->handle($customer, $event, $event->boardingPoints->first()->id, Offer::Standard, PassType::RoundTrip, 1, SalesChannel::App);
    $payment = app(InitiatePayment::class)->handle($order, PaymentMethod::Wave, $customer->phone)['payment'];
    app(ApplyPaymentResult::class)->handle($payment, PaymentStatus::Succeeded);

    return $order->passes()->sole();
}

beforeEach(function () {
    $this->customer = Customer::factory()->create(['phone' => '+2250707080839']);
    Sanctum::actingAs($this->customer, ['customer']);
});

it('affiche mes Pass avec un jeton QR vérifiable (SP-14 à SP-20)', function () {
    $pass = paidPass($this->customer);

    $this->getJson('/api/v1/me/passes')->assertOk()->assertJsonPath('data.0.code', $pass->code);

    $token = $this->getJson("/api/v1/passes/{$pass->code}")
        ->assertOk()
        ->assertJsonPath('data.status', 'valide')
        ->assertJsonPath('data.outbound.commune', 'Yopougon')
        ->assertJsonPath('data.return.pickup_zone', 'Parking P5 · porte Est')
        ->assertJsonPath('data.transferable', true)
        ->json('data.qr_token');

    expect(app(PassTokenService::class)->verify($token)['p'])->toBe($pass->id);
});

it('offre un Pass : nouveau détenteur, nouvelle version, ancien lien invalide (SP-19, RG-04)', function () {
    $pass = paidPass($this->customer);
    $oldUrl = PassNotifier::publicUrl($pass);

    $this->get($oldUrl)->assertOk()->assertSee($pass->code);

    $this->postJson("/api/v1/passes/{$pass->code}/transfer", ['phone' => '+33 6 12 34 56 78'])->assertOk();

    $pass->refresh();
    expect($pass->holder->phone)->toBe('+33612345678')
        ->and($pass->token_version)->toBe(2)
        ->and($pass->transfers()->count())->toBe(1);

    $this->get($oldUrl)->assertStatus(410);
    $this->get(PassNotifier::publicUrl($pass))->assertOk();
    $this->getJson("/api/v1/passes/{$pass->code}")->assertNotFound();
});

it('refuse d\'offrir un Pass dont l\'aller est scanné', function () {
    $pass = paidPass($this->customer);
    $pass->transitionTo(PassStatus::OutboundUsed);

    $this->postJson("/api/v1/passes/{$pass->code}/transfer", ['phone' => '0505050505'])
        ->assertUnprocessable()->assertJsonPath('code', 'PASS_NOT_TRANSFERABLE');
});

it('protège le lien public par une signature', function () {
    $pass = paidPass($this->customer);

    $this->get("/pass/{$pass->code}?v=1")->assertForbidden();
});

it('interdit les transitions de statut non prévues', function () {
    $pass = paidPass($this->customer);

    expect(fn () => $pass->transitionTo(PassStatus::Refunded))->toThrow(LogicException::class);
});
