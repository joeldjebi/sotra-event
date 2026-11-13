<?php

use App\Actions\Orders\CreateOrder;
use App\Actions\Payments\ApplyPaymentResult;
use App\Actions\Payments\InitiatePayment;
use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\OrderStatus;
use App\Enums\PassType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Services\Reporting\PlatformMetrics;
use Illuminate\Support\Carbon;

/**
 * Commande créée à $at puis (sauf $pay = false) payée deux minutes plus tard, par le vrai parcours d'achat.
 */
function sale(Event $event, string $at, int $quantity = 1, PassType $type = PassType::RoundTrip, SalesChannel $channel = SalesChannel::Web, ?Customer $customer = null, bool $pay = true): Order
{
    test()->travelTo(Carbon::parse($at));
    $order = app(CreateOrder::class)->handle($customer ?? Customer::factory()->create(), $event, $event->boardingPoints->first()->id, Offer::Standard, $type, $quantity, $channel);

    if ($pay) {
        $payment = app(InitiatePayment::class)->handle($order, PaymentMethod::Wave, '0701020304')['payment'];
        test()->travel(2)->minutes();
        app(ApplyPaymentResult::class)->handle($payment, PaymentStatus::Succeeded);
    }

    test()->travelTo(Carbon::parse('2026-11-10 12:00'));

    return $order->fresh();
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-10 12:00'));
    $this->event = eventOnSale(seats: 50);
});

it('calcule le CA encaissé TTC, la commission, la part compagnie et les frais de la période', function () {
    sale($this->event, '2026-11-09 10:00', 3);   // 3 × 3 000 + 2 % = 9 180
    sale($this->event, '2026-11-10 09:00', 2, PassType::Outbound); // 2 × 2 000 + 2 % = 4 080
    sale($this->event, '2026-11-10 09:30', 4, pay: false); // en attente : hors CA
    sale($this->event, '2026-10-01 10:00', 5); // hors période

    $summary = (new PlatformMetrics(7))->summary()['current'];

    expect($summary)->toMatchArray([
        'revenue' => 13260,
        'passes' => 5,
        'orders' => 2,
        'commission' => 1300,
        'fees' => 260,
        'company_share' => 11700,
        'customers' => 2,
    ])->and($summary['commission'] + $summary['company_share'] + $summary['fees'])->toBe($summary['revenue']);
});

it('n\'inclut pas les commandes à rembourser dans le chiffre d\'affaires', function () {
    $order = sale($this->event, '2026-11-09 10:00', 2);
    $order->update(['status' => OrderStatus::RefundRequired]);

    $metrics = new PlatformMetrics(7);

    expect($metrics->summary()['current']['revenue'])->toBe(0)
        ->and($metrics->alerts())->toMatchArray(['refund_count' => 1, 'refund_amount' => 6120]);
});

it('filtre par période glissante et compare à la période précédente', function () {
    sale($this->event, '2026-11-09 10:00', 4);  // période des 7 derniers jours
    sale($this->event, '2026-10-30 10:00', 2);  // 7 jours précédents
    sale($this->event, '2026-10-20 10:00', 1);  // dans les 30 jours seulement

    $week = (new PlatformMetrics(7))->summary();
    $month = (new PlatformMetrics(30))->summary();

    expect($week['current']['passes'])->toBe(4)
        ->and($week['previous']['passes'])->toBe(2)
        ->and($week['changes']['passes'])->toBe(1.0)
        ->and($month['current']['passes'])->toBe(7)
        ->and($month['changes']['passes'])->toBeNull();
});

it('donne une série quotidienne complète, jours sans vente à zéro', function () {
    sale($this->event, '2026-11-08 20:00', 2);
    sale($this->event, '2026-11-10 08:00', 1);
    sale($this->event, '2026-11-10 09:00', 3);

    $daily = (new PlatformMetrics(7))->dailySales();

    expect($daily)->toHaveCount(7)
        ->and($daily[0]['date']->toDateString())->toBe('2026-11-04')
        ->and(array_column($daily, 'passes'))->toBe([0, 0, 0, 0, 2, 0, 4])
        ->and($daily[6]['revenue'])->toBe(12240);
});

it('répartit les ventes par canal et par offre', function () {
    sale($this->event, '2026-11-09 10:00', 3, channel: SalesChannel::Web);
    sale($this->event, '2026-11-09 11:00', 1, channel: SalesChannel::App);

    $metrics = new PlatformMetrics(7);
    $channels = collect($metrics->salesByChannel())->keyBy('key');

    expect($channels->keys()->all())->toBe(['web', 'app', 'whatsapp'])
        ->and($channels['web']['passes'])->toBe(3)
        ->and($channels['web']['share'])->toBe(0.75)
        ->and($channels['whatsapp']['passes'])->toBe(0)
        ->and(collect($metrics->salesByOffer())->firstWhere('key', 'standard')['share'])->toEqual(1);
});

it('calcule le remplissage sur le segment le plus chargé, sans les places en attente de paiement', function () {
    sale($this->event, '2026-11-09 10:00', 3);                       // aller + retour
    sale($this->event, '2026-11-09 11:00', 2, PassType::Outbound);   // aller seulement
    sale($this->event, '2026-11-10 11:55', 4, pay: false);           // places bloquées, non vendues

    $fill = (new PlatformMetrics(7))->eventFill();

    expect($fill)->toHaveCount(1)
        ->and($fill[0]['sold'])->toBe(5)
        ->and($fill[0]['capacity'])->toBe(50)
        ->and($fill[0]['ratio'])->toBe(0.1)
        ->and($fill[0]['offers'][0])->toMatchArray(['offer' => Offer::Standard, 'sold' => 5, 'capacity' => 50]);
});

it('filtre par compagnie de transport', function () {
    // Autre mois : les codes de Pass (ET-AAMM-NNNN-COM) ne sont uniques que par mois et commune.
    $other = eventOnSale(seats: 20, attributes: ['company_id' => Company::factory()->create()->id]);
    $other->update(['starts_at' => '2026-12-05 18:00', 'ends_at' => '2026-12-05 23:00']);
    sale($this->event, '2026-11-09 10:00', 2);
    sale($other, '2026-11-09 10:00', 5);

    $all = new PlatformMetrics(7);
    $mine = new PlatformMetrics(7, $this->event->company_id);

    expect($all->summary()['current']['passes'])->toBe(7)
        ->and($mine->summary()['current']['passes'])->toBe(2)
        ->and($mine->eventFill())->toHaveCount(1)
        ->and($mine->snapshot()['events_on_sale'])->toBe(1)
        ->and($all->snapshot()['events_on_sale'])->toBe(2);
});

it('compte les commandes en attente et signale les paiements non confirmés depuis plus de 30 minutes', function () {
    $stale = sale($this->event, '2026-11-10 11:00', 1, pay: false);
    $this->travelTo(Carbon::parse('2026-11-10 11:00'));
    app(InitiatePayment::class)->handle($stale, PaymentMethod::OrangeMoney, '0701020304');
    $this->travelTo(Carbon::parse('2026-11-10 12:00'));
    sale($this->event, '2026-11-10 11:58', 2, pay: false);

    $metrics = new PlatformMetrics(7);

    expect($metrics->snapshot())->toMatchArray(['pending_orders' => 1, 'pending_amount' => 6120])
        ->and($metrics->alerts())->toMatchArray(['stale_payments' => 1, 'stale_amount' => 3060]);
});

it('compte les événements par étape, y compris pour un administrateur hors scopes', function () {
    Event::factory()->count(2)->create(['status' => EventStatus::Draft]);
    Event::factory()->create(['status' => EventStatus::Cancelled]);

    $pipeline = (new PlatformMetrics(30))->pipeline();
    $stages = collect($pipeline['stages'])->mapWithKeys(fn ($stage) => [$stage['status']->value => $stage['count']]);

    expect($stages->all())->toBe(['brouillon' => 2, 'propose' => 0, 'en_negociation' => 0, 'accepte' => 0, 'en_vente' => 1])
        ->and(collect($pipeline['others'])->firstWhere('status', EventStatus::Cancelled)['count'])->toBe(1);
});
