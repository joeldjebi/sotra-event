<?php

use App\Enums\Offer;
use App\Enums\PassType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pass;
use App\Services\Sales\PassCodeGenerator;
use App\Services\Sales\PassTokenService;
use App\Services\Sales\PricingService;

it('calcule frais, commission et part compagnie (RG-13)', function () {
    $event = eventOnSale();
    $quote = app(PricingService::class)->quote($event, $event->boardingPoints->first(), Offer::Standard, PassType::RoundTrip, 1);

    expect($quote->unitPrice)->toBe(3000)
        ->and($quote->fee())->toBe(60)
        ->and($quote->total())->toBe(3060)
        ->and($quote->commission())->toBe(300)
        ->and($quote->companyAmount())->toBe(2700);
});

it('arrondit les pourcentages à l\'unité (RG-16)', function (int $amount, string $rate, int $expected) {
    expect(PricingService::percent($amount, $rate))->toBe($expected);
})->with([[3000, '2.00', 60], [3500, '2.00', 70], [3060, '8.00', 245], [2525, '2.00', 51], [1, '10.00', 0]]);

it('génère des codes ET-AAMM-NNNN-COM séquentiels par commune (RG-02)', function () {
    $event = eventOnSale();
    $codes = app(PassCodeGenerator::class);

    expect($codes->next($event, 'YOP'))->toBe('ET-2611-0001-YOP')
        ->and($codes->next($event, 'YOP'))->toBe('ET-2611-0002-YOP')
        ->and($codes->next($event, 'abo'))->toBe('ET-2611-0001-ABO');
});

it('signe un jeton QR vérifiable et détecte toute falsification (NF-01)', function () {
    $event = eventOnSale();
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id, 'event_id' => $event->id, 'boarding_point_id' => $event->boardingPoints->first()->id]);
    $pass = Pass::factory()->create(['order_id' => $order->id, 'event_id' => $event->id, 'boarding_point_id' => $order->boarding_point_id, 'holder_id' => $customer->id]);
    $tokens = app(PassTokenService::class);

    $token = $tokens->sign($pass);
    $payload = $tokens->verify($token);

    expect($payload)->toMatchArray(['p' => $pass->id, 'e' => $event->id, 'o' => 'standard', 't' => 'aller_retour', 'v' => 1])
        ->and(strlen($token))->toBeLessThan(200);

    [$prefix, $body, $signature] = explode('.', $token);
    $forged = rtrim(strtr(base64_encode((string) json_encode(['p' => 999, 'e' => $event->id, 'b' => 1, 'o' => 'gold', 't' => 'aller_retour', 'v' => 1])), '+/', '-_'), '=');

    expect($tokens->verify("{$prefix}.{$forged}.{$signature}"))->toBeNull()
        ->and($tokens->verify('n\'importe quoi'))->toBeNull()
        ->and(base64_decode($tokens->publicKeyBase64()))->toHaveLength(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
});
