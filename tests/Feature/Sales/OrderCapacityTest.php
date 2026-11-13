<?php

use App\Actions\Orders\CreateOrder;
use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\OrderStatus;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\SalesChannel;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Services\Sales\CapacityService;

function order(int $quantity, PassType $type = PassType::RoundTrip, ?Event $event = null, SalesChannel $channel = SalesChannel::Web): Order
{
    $event ??= test()->event;

    return app(CreateOrder::class)->handle(Customer::factory()->create(), $event, $event->boardingPoints->first()->id, Offer::Standard, $type, $quantity, $channel);
}

beforeEach(fn () => $this->event = eventOnSale(seats: 10));

it('bloque les places pendant 15 minutes et fige les montants (RG-08, RG-15)', function () {
    $order = order(3);

    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->expires_at->diffInMinutes(now(), true))->toEqualWithDelta(15, 0.1)
        ->and($order->total)->toBe(9180)
        ->and($order->passes)->toHaveCount(3)
        ->and($order->passes->pluck('status')->unique()->all())->toBe([PassStatus::PendingPayment])
        ->and(app(CapacityService::class)->remaining($this->event, Offer::Standard, PassType::RoundTrip))->toBe(7);
});

it('refuse de vendre au-delà de la capacité de l\'offre (RG-08)', function () {
    order(8);

    expect(fn () => order(3))->toThrow(fn (ApiException $exception) => expect($exception->errorCode)->toBe('CAPACITY_REACHED')->and($exception->status)->toBe(409));

    expect(order(2)->quantity)->toBe(2);
    expect(fn () => order(1))->toThrow(ApiException::class);
});

it('compte les places par segment : un aller simple ne consomme pas le retour (RG-05)', function () {
    order(10, PassType::Outbound);
    $capacity = app(CapacityService::class);

    expect($capacity->remaining($this->event, Offer::Standard, PassType::Outbound))->toBe(0)
        ->and($capacity->remaining($this->event, Offer::Standard, PassType::Return))->toBe(10)
        ->and($capacity->remaining($this->event, Offer::Standard, PassType::RoundTrip))->toBe(0)
        ->and(order(4, PassType::Return)->quantity)->toBe(4);
});

it('libère les places des commandes expirées', function () {
    $order = order(10);
    $this->travel(16)->minutes();

    expect(app(CapacityService::class)->remaining($this->event, Offer::Standard, PassType::RoundTrip))->toBe(10);

    $this->artisan('orders:expire')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Expired)
        ->and($order->passes()->pluck('status')->unique()->all())->toBe([PassStatus::Expired]);
});

it('refuse une commande hors vente, hors canal ou hors limites', function (Closure $act, string $code) {
    expect(fn () => $act())->toThrow(fn (ApiException $exception) => expect($exception->errorCode)->toBe($code));
})->with([
    'événement non en vente' => [function () {
        test()->event->update(['status' => EventStatus::Accepted]);
        order(1);
    }, 'EVENT_NOT_ON_SALE'],
    'canal fermé' => [fn () => order(1, channel: SalesChannel::WhatsApp), 'CHANNEL_CLOSED'],
    'trop de Pass' => [fn () => order(11), 'QUANTITY_OUT_OF_RANGE'],
    'événement terminé' => [function () {
        test()->travelTo(now()->setDate(2026, 11, 16));
        order(1);
    }, 'EVENT_NOT_ON_SALE'],
]);

it('crée le Pass offert au nom du bénéficiaire (SP-08)', function () {
    $buyer = Customer::factory()->create();

    $order = app(CreateOrder::class)->handle($buyer, $this->event, $this->event->boardingPoints->first()->id, Offer::Standard, PassType::RoundTrip, 1, SalesChannel::Web, '+33 6 12 34 56 78');

    expect($order->customer_id)->toBe($buyer->id)
        ->and($order->beneficiary_phone)->toBe('+33612345678')
        ->and($order->passes->first()->holder->phone)->toBe('+33612345678');
});
