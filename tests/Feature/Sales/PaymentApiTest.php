<?php

use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PassStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OutboundMessage;
use App\Models\Pass;
use App\Models\Payment;
use App\Models\SmsMessage;
use App\Models\Staff;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->event = eventOnSale(seats: 10);
    $this->customer = Customer::factory()->create(['phone' => '+2250707080839']);
    Sanctum::actingAs($this->customer, ['customer']);
});

function apiOrder(int $quantity = 2): Order
{
    $reference = test()->postJson('/api/v1/orders', [
        'event_id' => test()->event->id,
        'boarding_point_id' => test()->event->boardingPoints->first()->id,
        'offer' => 'standard',
        'pass_type' => 'aller_retour',
        'quantity' => $quantity,
    ])->assertCreated()->json('data.reference');

    return Order::query()->where('reference', $reference)->sole();
}

/**
 * Envoie le webhook que l'agrégateur simulé enverrait.
 */
function sendWebhook(Payment $payment, PaymentStatus $status): TestResponse
{
    $webhook = app(PaymentGateway::class)->simulate($payment, $status);

    return test()->call('POST', '/api/v1/payments/webhook/fake', [], [], [], ['HTTP_X_SIGNATURE' => $webhook['signature'], 'CONTENT_TYPE' => 'application/json'], $webhook['body']);
}

it('liste les événements en vente et leur détail (SP-01 à SP-05)', function () {
    $this->getJson('/api/v1/events')
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->event->id)
        ->assertJsonPath('data.0.from_price', 2000)
        ->assertJsonPath('data.0.communes_count', 1);

    $this->getJson("/api/v1/events/{$this->event->id}")
        ->assertOk()
        ->assertJsonPath('data.boarding_points.0.commune_code', 'YOP')
        ->assertJsonPath('data.offers.0.prices.0', ['zone' => 1, 'pass_type' => 'aller_retour', 'price' => 3000])
        ->assertJsonPath('data.offers.0.remaining.aller_retour', 10)
        ->assertJsonPath('data.pickup_zones.0.name', 'Parking P5 · porte Est');
});

it('calcule un devis sans bloquer de place', function () {
    $this->postJson('/api/v1/orders/quote', [
        'event_id' => $this->event->id, 'boarding_point_id' => $this->event->boardingPoints->first()->id,
        'offer' => 'standard', 'pass_type' => 'aller_retour', 'quantity' => 2,
    ])->assertOk()->assertJsonPath('data.total', 6120)->assertJsonPath('data.fee_amount', 120);

    expect(Order::query()->count())->toBe(0);
});

it('paie une commande et émet les Pass à la confirmation du webhook (P2)', function () {
    $order = apiOrder(2);

    $this->postJson("/api/v1/orders/{$order->reference}/pay", ['method' => 'orange_money', 'msisdn' => '07 07 08 08 39'])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'en_attente')
        ->assertJsonPath('data.amount', 6120);

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);

    sendWebhook($order->payments()->sole(), PaymentStatus::Succeeded)->assertOk();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->passes->pluck('status')->unique()->all())->toBe([PassStatus::Valid])
        ->and($order->passes->pluck('code')->sort()->values()->all())->toBe(['ET-2611-0001-YOP', 'ET-2611-0002-YOP'])
        ->and(SmsMessage::query()->where('to', '+2250707080839')->count())->toBe(2)
        ->and(OutboundMessage::query()->where('channel', 'whatsapp')->count())->toBe(2);

    $this->getJson("/api/v1/orders/{$order->reference}")->assertJsonPath('data.status', 'payee');
});

it('ne crée jamais deux Pass pour un même paiement, même si le webhook arrive 3 fois (PA-02)', function () {
    $order = apiOrder(1);
    $this->postJson("/api/v1/orders/{$order->reference}/pay", ['method' => 'wave', 'msisdn' => '0707080839']);
    $payment = $order->payments()->sole();
    $webhook = app(PaymentGateway::class)->simulate($payment, PaymentStatus::Succeeded);

    foreach (range(1, 3) as $attempt) {
        $this->call('POST', '/api/v1/payments/webhook/fake', [], [], [], ['HTTP_X_SIGNATURE' => $webhook['signature']], $webhook['body'])->assertOk();
    }

    expect(Pass::query()->whereNotNull('code')->count())->toBe(1)
        ->and(SmsMessage::query()->count())->toBe(1);
});

it('rejette un webhook à la signature invalide', function () {
    $order = apiOrder(1);
    $this->postJson("/api/v1/orders/{$order->reference}/pay", ['method' => 'wave', 'msisdn' => '0707080839']);
    $webhook = app(PaymentGateway::class)->simulate($order->payments()->sole(), PaymentStatus::Succeeded);

    $this->call('POST', '/api/v1/payments/webhook/fake', [], [], [], ['HTTP_X_SIGNATURE' => 'faux'], $webhook['body'])->assertUnauthorized();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('laisse réessayer après un paiement refusé', function () {
    $order = apiOrder(1);
    $this->postJson("/api/v1/orders/{$order->reference}/pay", ['method' => 'mtn_momo', 'msisdn' => '0505050505']);
    sendWebhook($order->payments()->sole(), PaymentStatus::Failed)->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);

    $this->postJson("/api/v1/orders/{$order->reference}/pay", ['method' => 'carte'])
        ->assertStatus(202)
        ->assertJsonPath('data.instructions.redirect_url', fn ($url) => str_contains($url, '/simulateur-paiement/'));
});

it('confirme par la réconciliation un paiement dont le webhook n\'est jamais arrivé (PA-03)', function () {
    $order = apiOrder(1);
    $this->postJson("/api/v1/orders/{$order->reference}/pay", ['method' => 'wave', 'msisdn' => '0707080839']);
    app(PaymentGateway::class)->simulate($order->payments()->sole(), PaymentStatus::Succeeded);

    $this->travel(3)->minutes();
    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('signale à rembourser un paiement tardif quand les places ont été revendues', function () {
    $order = apiOrder(10);
    $this->postJson("/api/v1/orders/{$order->reference}/pay", ['method' => 'wave', 'msisdn' => '0707080839']);
    $payment = $order->payments()->sole();

    $this->travel(16)->minutes();
    $this->artisan('orders:expire');
    apiOrder(10);

    sendWebhook($payment, PaymentStatus::Succeeded)->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::RefundRequired)
        ->and($order->passes()->whereNotNull('code')->count())->toBe(0);
});

it('refuse de payer une commande expirée ou celle d\'un autre spectateur', function () {
    $order = apiOrder(1);
    $this->travel(16)->minutes();

    $this->postJson("/api/v1/orders/{$order->reference}/pay", ['method' => 'wave', 'msisdn' => '0707080839'])
        ->assertUnprocessable()->assertJsonPath('code', 'ORDER_NOT_PAYABLE');

    Sanctum::actingAs(Customer::factory()->create(), ['customer']);
    $this->getJson("/api/v1/orders/{$order->reference}")->assertNotFound();
});

it('réserve les routes spectateur aux jetons « customer »', function () {
    Sanctum::actingAs(Staff::factory()->create(), ['conveyor']);

    $this->getJson('/api/v1/me/passes')->assertForbidden();
});
