<?php

namespace Database\Seeders;

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
use App\Exceptions\ApiException;
use App\Models\BoardingPoint;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Services\Sales\CapacityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Historique de ventes de démonstration (~45 jours) pour l'événement en vente des données de démo,
 * via le vrai parcours d'achat (commande, paiement, confirmation de l'agrégateur) en antidatant l'horloge.
 *
 * Demande croissante à l'approche du concert, trois canaux, trois offres, quelques commandes abandonnées
 * (expirées), échouées, en attente, et deux paiements tardifs à rembourser (offre Gold complète entre-temps).
 * Jamais au-delà de la capacité : la vente passe par CreateOrder, qui la contrôle.
 *
 * php artisan db:seed --class=DemoSalesSeeder (après DemoEventsSeeder ; à lancer sur une base sans ventes).
 */
class DemoSalesSeeder extends Seeder
{
    private const DAYS = 45;

    private int $phone = 0;

    /** @var list<Customer> */
    private array $customers = [];

    public function __construct(
        private readonly CreateOrder $createOrder,
        private readonly InitiatePayment $initiatePayment,
        private readonly ApplyPaymentResult $applyPaymentResult,
        private readonly CapacityService $capacity,
    ) {}

    public function run(): void
    {
        $event = Event::query()->withoutGlobalScopes()->where('status', EventStatus::OnSale)->with('boardingPoints')->orderBy('starts_at')->first()
            ?? throw new RuntimeException('Aucun événement en vente : lancez d\'abord DemoEventsSeeder.');

        if (Order::query()->where('event_id', $event->id)->exists()) {
            $this->command?->warn("Des commandes existent déjà pour « {$event->name} » : historique non regénéré.");

            return;
        }

        if ($event->ends_at->isPast()) {
            throw new RuntimeException("« {$event->name} » est terminé : impossible d'y vendre des Pass.");
        }

        mt_srand(20261115);
        $realNow = Carbon::now();
        $start = $realNow->copy()->startOfDay()->subDays(self::DAYS);
        $points = $event->boardingPoints->values();

        try {
            for ($day = 0; $day <= self::DAYS; $day++) {
                $date = $start->copy()->addDays($day);
                // Demande croissante (montée en puissance jusqu'au jour J), plus forte le week-end.
                $orders = (int) round((2 + 26 * ($day / self::DAYS) ** 1.8) * ($date->isWeekend() ? 1.35 : 1.0));

                if ($date->isSameDay($realNow)) {
                    $orders = (int) round($orders * min(1, $realNow->hour / 24));
                }

                for ($i = 0; $i < $orders; $i++) {
                    $at = $date->copy()->setTime($this->hour(), mt_rand(0, 59), mt_rand(0, 59));

                    if ($at->gte($realNow->copy()->subHour())) {
                        continue;
                    }

                    $this->sell($event, $points->all(), $at);
                }
            }

            $this->lateRefunds($event, $points->all(), $realNow->copy()->subDay()->setTime(20, 0));
        } finally {
            Carbon::setTestNow();
        }

        // Commandes en cours de paiement « maintenant » (places bloquées) et paiement non confirmé de longue date.
        $this->pending($event, $points->all(), $realNow);

        Artisan::call('orders:expire');

        $paid = Order::query()->where('event_id', $event->id)->where('status', OrderStatus::Paid);
        $this->command?->info(sprintf(
            '%d commandes payées (%d Pass) pour « %s ».',
            (clone $paid)->count(),
            (clone $paid)->sum('quantity'),
            $event->name,
        ));
    }

    /**
     * Une vente : commande, puis paiement réussi (88 %), échoué (5 %) ou abandonné (7 %, la commande expire).
     *
     * @param  list<BoardingPoint>  $points
     */
    private function sell(Event $event, array $points, Carbon $at): void
    {
        Carbon::setTestNow($at);

        $offer = $this->offer($event);
        $order = $this->order($event, $points, $offer, $this->passType(), $this->quantity());

        if ($order === null) {
            return;
        }

        $roll = mt_rand(1, 100);

        if ($roll > 93) {
            return;
        }

        $payment = $this->initiatePayment->handle($order, $this->method(), $this->msisdn())['payment'];
        Carbon::setTestNow($at->copy()->addMinutes(mt_rand(1, 6)));

        $this->applyPaymentResult->handle(
            $payment,
            $roll > 88 ? PaymentStatus::Failed : PaymentStatus::Succeeded,
            $roll > 88 ? 'Solde insuffisant' : null,
        );
    }

    /**
     * Deux paiements Gold confirmés après l'expiration de la commande, alors que l'offre est devenue complète :
     * les commandes passent « à rembourser » (ApplyPaymentResult).
     *
     * @param  list<BoardingPoint>  $points
     */
    private function lateRefunds(Event $event, array $points, Carbon $at): void
    {
        Carbon::setTestNow($at);
        $late = array_values(array_filter([
            $this->order($event, $points, Offer::Gold, PassType::RoundTrip, 2),
            $this->order($event, $points, Offer::Gold, PassType::RoundTrip, 1),
        ]));
        $payments = array_map(fn (Order $order) => $this->initiatePayment->handle($order, PaymentMethod::Wave, $this->msisdn())['payment'], $late);

        // L'agrégateur tarde : les commandes expirent et d'autres clients achètent les dernières places Gold.
        $at = $at->copy()->addMinutes(20);

        while (($remaining = $this->remaining($event, Offer::Gold)) > 0) {
            $at = $at->addMinutes(mt_rand(2, 9));
            Carbon::setTestNow($at);
            $order = $this->order($event, $points, Offer::Gold, PassType::RoundTrip, min($remaining, mt_rand(1, 3)));

            if ($order === null) {
                break;
            }

            $payment = $this->initiatePayment->handle($order, $this->method(), $this->msisdn())['payment'];
            $this->applyPaymentResult->handle($payment, PaymentStatus::Succeeded);
        }

        Carbon::setTestNow($at->copy()->addMinutes(10));

        foreach ($payments as $payment) {
            $this->applyPaymentResult->handle($payment, PaymentStatus::Succeeded);
        }
    }

    /**
     * @param  list<BoardingPoint>  $points
     */
    private function pending(Event $event, array $points, Carbon $now): void
    {
        foreach ([3, 7, 11] as $minutesAgo) {
            Carbon::setTestNow($now->copy()->subMinutes($minutesAgo));
            $order = $this->order($event, $points, Offer::Standard, PassType::RoundTrip, mt_rand(1, 3));

            if ($order !== null && $minutesAgo > 5) {
                $this->initiatePayment->handle($order, PaymentMethod::OrangeMoney, $this->msisdn());
            }
        }

        // Paiement resté sans réponse de l'agrégateur depuis 2 h : à réconcilier.
        Carbon::setTestNow($now->copy()->subHours(2));
        $order = $this->order($event, $points, Offer::Premium, PassType::RoundTrip, 2);

        if ($order !== null) {
            $this->initiatePayment->handle($order, PaymentMethod::MtnMomo, $this->msisdn());
        }

        Carbon::setTestNow();
    }

    /**
     * @param  list<BoardingPoint>  $points
     */
    private function order(Event $event, array $points, Offer $offer, PassType $type, int $quantity): ?Order
    {
        // Environ un acheteur sur huit revient pour une deuxième commande.
        $customer = $this->customers !== [] && mt_rand(1, 8) === 1
            ? $this->customers[mt_rand(0, count($this->customers) - 1)]
            : $this->customers[] = Customer::query()->create([
                'phone' => $this->msisdn(),
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'locale' => 'fr',
            ]);

        try {
            return $this->createOrder->handle($customer, $event, $this->point($points)->id, $offer, $type, $quantity, $this->channel());
        } catch (ApiException) {
            return null;
        }
    }

    private function offer(Event $event): Offer
    {
        $roll = mt_rand(1, 100);

        // Gold : on garde quelques places pour le scénario des paiements tardifs.
        if ($roll > 93 && $this->remaining($event, Offer::Gold) > 24) {
            return Offer::Gold;
        }

        return $roll > 70 ? Offer::Premium : Offer::Standard;
    }

    private function remaining(Event $event, Offer $offer): int
    {
        return $this->capacity->remaining($event, $offer, PassType::RoundTrip) ?? 0;
    }

    /**
     * Points pondérés : Yopougon et Abobo, communes les plus peuplées, en tête.
     *
     * @param  list<BoardingPoint>  $points
     */
    private function point(array $points): BoardingPoint
    {
        $weights = [34, 26, 18, 12, 10];
        $roll = mt_rand(1, array_sum(array_slice($weights, 0, count($points))));

        foreach ($points as $index => $point) {
            $roll -= $weights[$index] ?? 5;

            if ($roll <= 0) {
                return $point;
            }
        }

        return $points[0];
    }

    private function channel(): SalesChannel
    {
        $roll = mt_rand(1, 100);

        return $roll <= 46 ? SalesChannel::Web : ($roll <= 80 ? SalesChannel::App : SalesChannel::WhatsApp);
    }

    private function passType(): PassType
    {
        $roll = mt_rand(1, 100);

        return $roll <= 86 ? PassType::RoundTrip : ($roll <= 95 ? PassType::Outbound : PassType::Return);
    }

    private function quantity(): int
    {
        return [1, 1, 1, 2, 2, 2, 2, 3, 4, 5][mt_rand(0, 9)];
    }

    private function method(): PaymentMethod
    {
        return [PaymentMethod::OrangeMoney, PaymentMethod::OrangeMoney, PaymentMethod::Wave, PaymentMethod::Wave, PaymentMethod::MtnMomo, PaymentMethod::MoovMoney][mt_rand(0, 5)];
    }

    private function hour(): int
    {
        return [8, 9, 11, 12, 12, 13, 17, 18, 19, 19, 20, 20, 21, 21, 22][mt_rand(0, 14)];
    }

    private function msisdn(): string
    {
        $this->phone++;

        return '+225070'.str_pad((string) (1_000_000 + $this->phone), 7, '0', STR_PAD_LEFT);
    }
}
