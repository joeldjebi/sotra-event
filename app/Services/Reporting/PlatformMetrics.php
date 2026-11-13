<?php

namespace App\Services\Reporting;

use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\OrderStatus;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\PaymentStatus;
use App\Enums\SalesChannel;
use App\Models\Event;
use App\Models\EventOffer;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Indicateurs de la console ACCES EVENT (AD-07) calculés par agrégats SQL.
 *
 * Période glissante de N jours se terminant aujourd'hui, comparée aux N jours précédents. Une vente est
 * comptée le jour de son encaissement (paid_at) ; les commandes « à rembourser » ne sont pas du chiffre
 * d'affaires. Les instantanés (commandes en attente, remplissage, pipeline) ignorent la période.
 * Filtre facultatif par compagnie de transport.
 */
final class PlatformMetrics
{
    /** Périodes proposées (en jours). */
    public const PERIODS = [7, 30, 90];

    /** Délai au-delà duquel un paiement non confirmé est signalé (minutes). */
    public const STALE_PAYMENT_MINUTES = 30;

    public readonly CarbonImmutable $now;

    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $previousFrom;

    public function __construct(
        public readonly int $days = 30,
        public readonly ?int $companyId = null,
        ?CarbonInterface $now = null,
    ) {
        $this->now = CarbonImmutable::instance($now ?? now());
        $this->from = $this->now->startOfDay()->subDays(max(1, $days) - 1);
        $this->previousFrom = $this->from->subDays(max(1, $days));
    }

    /**
     * Chiffres de la période, de la période précédente et variations (null si la période précédente est vide).
     *
     * @return array{current: array<string, int>, previous: array<string, int>, changes: array<string, float|null>}
     */
    public function summary(): array
    {
        $current = $this->totals($this->from, $this->now);
        $previous = $this->totals($this->previousFrom, $this->from);

        $changes = [];

        foreach ($current as $key => $value) {
            $changes[$key] = $previous[$key] === 0 ? null : ($value - $previous[$key]) / (float) $previous[$key];
        }

        return ['current' => $current, 'previous' => $previous, 'changes' => $changes];
    }

    /**
     * Ventes encaissées par jour, jours sans vente inclus (à zéro).
     *
     * @return list<array{date: CarbonImmutable, passes: int, revenue: int}>
     */
    public function dailySales(): array
    {
        $rows = $this->paidOrders($this->from, $this->now)
            ->selectRaw('date(paid_at) as day, coalesce(sum(quantity), 0) as passes, coalesce(sum(total), 0) as revenue')
            ->groupByRaw('date(paid_at)')
            ->get()
            ->keyBy(fn (object $row): string => substr((string) $row->day, 0, 10));

        $series = [];

        for ($day = $this->from; $day->lte($this->now); $day = $day->addDay()) {
            $row = $rows->get($day->toDateString());
            $series[] = ['date' => $day, 'passes' => (int) ($row->passes ?? 0), 'revenue' => (int) ($row->revenue ?? 0)];
        }

        return $series;
    }

    /**
     * Répartition des ventes de la période par offre (ordre Standard, Premium, Gold).
     *
     * @return list<array{key: string, label: string, passes: int, revenue: int, share: float}>
     */
    public function salesByOffer(): array
    {
        return $this->breakdown('offer', array_map(fn (Offer $offer): array => [$offer->value, $offer->label()], Offer::cases()));
    }

    /**
     * Répartition des ventes de la période par canal (canaux ouverts en V1).
     *
     * @return list<array{key: string, label: string, passes: int, revenue: int, share: float}>
     */
    public function salesByChannel(): array
    {
        $channels = array_filter(SalesChannel::cases(), fn (SalesChannel $channel): bool => $channel->isAvailable());

        return $this->breakdown('channel', array_values(array_map(fn (SalesChannel $channel): array => [
            $channel->value,
            $channel === SalesChannel::App ? 'Application' : $channel->label(),
        ], $channels)));
    }

    /**
     * Communes de départ les plus demandées sur la période.
     *
     * @return list<array{commune: string, passes: int, revenue: int}>
     */
    public function topCommunes(int $limit = 6): array
    {
        return $this->paidOrders($this->from, $this->now)
            ->join('boarding_points', 'boarding_points.id', '=', 'orders.boarding_point_id')
            ->selectRaw('boarding_points.commune as commune, sum(orders.quantity) as passes, sum(orders.total) as revenue')
            ->groupBy('boarding_points.commune')
            ->orderByDesc('passes')
            ->orderBy('commune')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => ['commune' => (string) $row->commune, 'passes' => (int) $row->passes, 'revenue' => (int) $row->revenue])
            ->values()
            ->all();
    }

    /**
     * Remplissage des événements en vente : places vendues / capacité (bus × places, RG-08), par offre.
     * Un aller-retour occupe une place à l'aller et au retour : on retient le segment le plus chargé.
     *
     * @return list<array{event: Event, sold: int, capacity: int, ratio: float|null, offers: list<array{offer: Offer, sold: int, capacity: int|null}>}>
     */
    public function eventFill(): array
    {
        $events = $this->events()
            ->where('status', EventStatus::OnSale)
            ->with(['company:id,name', 'offers'])
            ->orderBy('starts_at')
            ->get();

        $occupancy = $this->occupancy($events->modelKeys());

        return array_values(array_map(fn (Event $event): array => $this->fillFor($event, $occupancy), $events->all()));
    }

    /**
     * Événements à venir (hors brouillons, annulés et terminés), avec leur remplissage.
     *
     * @return list<array{event: Event, sold: int, capacity: int, ratio: float|null, offers: list<array{offer: Offer, sold: int, capacity: int|null}>}>
     */
    public function upcomingEvents(int $limit = 8): array
    {
        $events = $this->events()
            ->whereIn('status', [EventStatus::Proposed, EventStatus::Negotiating, EventStatus::Accepted, EventStatus::OnSale, EventStatus::Postponed])
            ->where('ends_at', '>=', $this->now)
            ->with(['company:id,name', 'venue:id,name', 'offers'])
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        $occupancy = $this->occupancy($events->modelKeys());

        return array_values(array_map(fn (Event $event): array => $this->fillFor($event, $occupancy), $events->all()));
    }

    /**
     * Nombre d'événements par étape du cycle de vie (brouillon → en vente), puis les autres statuts.
     *
     * @return array{stages: list<array{status: EventStatus, count: int}>, others: list<array{status: EventStatus, count: int}>}
     */
    public function pipeline(): array
    {
        $counts = $this->events()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $row = fn (EventStatus $status): array => ['status' => $status, 'count' => (int) ($counts[$status->value] ?? 0)];

        return [
            'stages' => array_map($row, [EventStatus::Draft, EventStatus::Proposed, EventStatus::Negotiating, EventStatus::Accepted, EventStatus::OnSale]),
            'others' => array_map($row, [EventStatus::Finished, EventStatus::Postponed, EventStatus::Cancelled]),
        ];
    }

    /**
     * Instantané : événements en vente et à venir, commandes en attente de paiement.
     *
     * @return array{events_on_sale: int, events_upcoming: int, pending_orders: int, pending_amount: int}
     */
    public function snapshot(): array
    {
        $pending = $this->orders()
            ->where('status', OrderStatus::PendingPayment)
            ->where('expires_at', '>', $this->now)
            ->toBase()
            ->selectRaw('count(*) as orders, coalesce(sum(total), 0) as amount')
            ->first();

        return [
            'events_on_sale' => $this->events()->where('status', EventStatus::OnSale)->count(),
            'events_upcoming' => $this->events()
                ->whereIn('status', [EventStatus::Proposed, EventStatus::Negotiating, EventStatus::Accepted, EventStatus::OnSale, EventStatus::Postponed])
                ->where('ends_at', '>=', $this->now)
                ->count(),
            'pending_orders' => (int) ($pending->orders ?? 0),
            'pending_amount' => (int) ($pending->amount ?? 0),
        ];
    }

    /**
     * Points d'attention : commandes à rembourser et paiements non confirmés depuis plus de 30 minutes.
     *
     * @return array{refund_count: int, refund_amount: int, refund_orders: Collection<int, Order>, stale_payments: int, stale_amount: int}
     */
    public function alerts(): array
    {
        $refunds = $this->orders()->where('status', OrderStatus::RefundRequired);
        $refundTotals = (clone $refunds)->toBase()->selectRaw('count(*) as orders, coalesce(sum(total), 0) as amount')->first();

        $stale = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('created_at', '<', $this->now->subMinutes(self::STALE_PAYMENT_MINUTES))
            ->when($this->companyId !== null, fn (Builder $query) => $query->whereIn('order_id', $this->orders()->select('id')))
            ->toBase()
            ->selectRaw('count(*) as payments, coalesce(sum(amount), 0) as amount')
            ->first();

        return [
            'refund_count' => (int) ($refundTotals->orders ?? 0),
            'refund_amount' => (int) ($refundTotals->amount ?? 0),
            'refund_orders' => $refunds->with(['event:id,name', 'boardingPoint:id,commune'])->latest('updated_at')->limit(4)->get(),
            'stale_payments' => (int) ($stale->payments ?? 0),
            'stale_amount' => (int) ($stale->amount ?? 0),
        ];
    }

    /**
     * Dernières commandes payées (toutes périodes).
     *
     * @return Collection<int, Order>
     */
    public function recentOrders(int $limit = 6): Collection
    {
        return $this->orders()
            ->where('status', OrderStatus::Paid)
            ->with(['event:id,name', 'boardingPoint:id,commune'])
            ->latest('paid_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{revenue: int, passes: int, orders: int, commission: int, fees: int, company_share: int, customers: int}
     */
    private function totals(CarbonInterface $from, CarbonInterface $to): array
    {
        $row = $this->paidOrders($from, $to)
            ->selectRaw('count(*) as orders')
            ->selectRaw('coalesce(sum(quantity), 0) as passes')
            ->selectRaw('coalesce(sum(total), 0) as revenue')
            ->selectRaw('coalesce(sum(commission_amount), 0) as commission')
            ->selectRaw('coalesce(sum(fee_amount), 0) as fees')
            ->selectRaw('coalesce(sum(company_amount), 0) as company_share')
            ->selectRaw('count(distinct customer_id) as customers')
            ->first();

        return [
            'revenue' => (int) ($row->revenue ?? 0),
            'passes' => (int) ($row->passes ?? 0),
            'orders' => (int) ($row->orders ?? 0),
            'commission' => (int) ($row->commission ?? 0),
            'fees' => (int) ($row->fees ?? 0),
            'company_share' => (int) ($row->company_share ?? 0),
            'customers' => (int) ($row->customers ?? 0),
        ];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $keys  valeur et libellé, dans l'ordre d'affichage
     * @return list<array{key: string, label: string, passes: int, revenue: int, share: float}>
     */
    private function breakdown(string $column, array $keys): array
    {
        $rows = $this->paidOrders($this->from, $this->now)
            ->selectRaw("{$column} as bucket, sum(quantity) as passes, sum(total) as revenue")
            ->groupBy($column)
            ->get()
            ->keyBy('bucket');

        $total = (int) $rows->sum('passes');

        return array_map(function (array $key) use ($rows, $total): array {
            $passes = (int) ($rows->get($key[0])->passes ?? 0);

            return [
                'key' => $key[0],
                'label' => $key[1],
                'passes' => $passes,
                'revenue' => (int) ($rows->get($key[0])->revenue ?? 0),
                'share' => $total === 0 ? 0.0 : $passes / $total,
            ];
        }, $keys);
    }

    /**
     * Places occupées par des Pass payés, par événement et offre, pour l'aller et le retour.
     *
     * @param  array<int, mixed>  $eventIds
     * @return array<int, array<string, array{outbound: int, return: int}>>
     */
    private function occupancy(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $outbound = [PassType::RoundTrip->value, PassType::Outbound->value];
        $return = [PassType::RoundTrip->value, PassType::Return->value];

        $map = [];

        Pass::query()
            ->toBase()
            ->selectRaw('event_id, offer')
            ->selectRaw('sum(case when pass_type in (?, ?) then 1 else 0 end) as outbound', $outbound)
            ->selectRaw('sum(case when pass_type in (?, ?) then 1 else 0 end) as inbound', $return)
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', [PassStatus::Valid, PassStatus::OutboundUsed, PassStatus::ReturnUsed, PassStatus::Completed])
            ->groupBy('event_id', 'offer')
            ->get()
            ->each(function (object $row) use (&$map): void {
                $map[(int) $row->event_id][(string) $row->offer] = ['outbound' => (int) $row->outbound, 'return' => (int) $row->inbound];
            });

        return $map;
    }

    /**
     * @param  array<int, array<string, array{outbound: int, return: int}>>  $occupancy
     * @return array{event: Event, sold: int, capacity: int, ratio: float|null, offers: list<array{offer: Offer, sold: int, capacity: int|null}>}
     */
    private function fillFor(Event $event, array $occupancy): array
    {
        $offers = $event->offers
            ->sortBy(fn (EventOffer $offer): int|false => array_search($offer->offer, Offer::cases(), true))
            ->values();

        $rows = [];
        $sold = 0;
        $capacity = 0;

        foreach ($offers as $offer) {
            $segments = $occupancy[$event->id][$offer->offer->value] ?? ['outbound' => 0, 'return' => 0];
            $offerSold = max($segments['outbound'], $segments['return']);
            $rows[] = ['offer' => $offer->offer, 'sold' => $offerSold, 'capacity' => $offer->capacity()];
            $sold += $offerSold;
            $capacity += $offer->capacity() ?? 0;
        }

        return [
            'event' => $event,
            'sold' => $sold,
            'capacity' => $capacity,
            'ratio' => $capacity > 0 ? min(1, $sold / $capacity) : null,
            'offers' => $rows,
        ];
    }

    /**
     * Commandes payées dont l'encaissement tombe dans [from, to[ (to inclus pour la période en cours).
     */
    private function paidOrders(CarbonInterface $from, CarbonInterface $to): QueryBuilder
    {
        return $this->orders()
            ->where('orders.status', OrderStatus::Paid)
            ->where('orders.paid_at', '>=', $from)
            ->where('orders.paid_at', $to->equalTo($this->now) ? '<=' : '<', $to)
            ->toBase();
    }

    /**
     * @return Builder<Order>
     */
    private function orders(): Builder
    {
        return Order::query()->when(
            $this->companyId !== null,
            fn (Builder $query) => $query->whereIn('orders.event_id', $this->events()->select('id')),
        );
    }

    /**
     * Événements, sans les scopes compagnie/promoteur (la console voit tout), filtrés par compagnie au besoin.
     *
     * @return Builder<Event>
     */
    private function events(): Builder
    {
        return Event::query()
            ->withoutGlobalScopes()
            ->when($this->companyId !== null, fn (Builder $query) => $query->where('company_id', $this->companyId));
    }
}
