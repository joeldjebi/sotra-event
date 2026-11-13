<?php

namespace App\Services\Reporting;

use App\Enums\Direction;
use App\Enums\Offer;
use App\Enums\OrderStatus;
use App\Enums\PassStatus;
use App\Enums\SalesChannel;
use App\Models\BoardingPoint;
use App\Models\Event;
use App\Models\Mission;
use App\Models\Order;
use App\Models\Pass;

/**
 * Ventes d'un événement pour la direction commerciale de la compagnie (CO-01 à CO-03) :
 * Pass vendus / places ouvertes, recette brute (hors frais), prix moyen, part compagnie, remplissage prévu,
 * répartition par offre et par canal, détail par point d'embarquement.
 */
class EventSalesReport
{
    /**
     * @return array{
     *     sold: int, capacity: int, gross: int, average_price: int, company_share: int, commission: int,
     *     buses_planned: int, seats_planned: int, fill_rate: int|null,
     *     by_offer: list<array{offer: Offer, passes: int, revenue: int}>,
     *     by_channel: list<array{channel: SalesChannel, passes: int, share: int}>,
     *     by_point: list<array{point: BoardingPoint, passes: int, by_offer: array<string, int>, buses: int, revenue: int}>
     * }
     */
    public function build(Event $event): array
    {
        $event->loadMissing(['offers', 'boardingPoints']);

        $passes = Pass::query()
            ->where('event_id', $event->id)
            ->whereIn('status', PassStatus::sold())
            ->selectRaw('boarding_point_id, offer, count(*) as passes, coalesce(sum(price), 0) as revenue, coalesce(sum(company_amount), 0) as company_share, coalesce(sum(commission_amount), 0) as commission')
            ->groupBy('boarding_point_id', 'offer')
            ->get();

        $sold = (int) $passes->sum('passes');
        $gross = (int) $passes->sum('revenue');
        $capacity = (int) $event->offers->sum(fn ($offer) => (int) $offer->capacity());

        $missions = Mission::query()->withoutGlobalScopes()->where('event_id', $event->id)->where('direction', Direction::Outbound)
            ->selectRaw('boarding_point_id, count(*) as buses, coalesce(sum(seats), 0) as seats')
            ->groupBy('boarding_point_id')
            ->get()
            ->keyBy('boarding_point_id');

        $busesPlanned = (int) $missions->sum('buses');
        $seatsPlanned = (int) $missions->sum('seats');

        $channels = Order::query()
            ->where('event_id', $event->id)
            ->where('status', OrderStatus::Paid)
            ->selectRaw('channel, coalesce(sum(quantity), 0) as passes')
            ->groupBy('channel')
            ->pluck('passes', 'channel');
        $channelTotal = max(1, (int) $channels->sum());

        $offerValue = fn ($row): string => $row->offer instanceof Offer ? $row->offer->value : (string) $row->offer;

        return [
            'sold' => $sold,
            'capacity' => $capacity,
            'gross' => $gross,
            'average_price' => $sold > 0 ? intdiv($gross + intdiv($sold, 2), $sold) : 0,
            'company_share' => (int) $passes->sum('company_share'),
            'commission' => (int) $passes->sum('commission'),
            'buses_planned' => $busesPlanned,
            'seats_planned' => $seatsPlanned,
            'fill_rate' => $seatsPlanned > 0 ? (int) round($sold / $seatsPlanned * 100) : null,
            'by_offer' => array_map(fn (Offer $offer) => [
                'offer' => $offer,
                'passes' => (int) $passes->filter(fn ($row) => $offerValue($row) === $offer->value)->sum('passes'),
                'revenue' => (int) $passes->filter(fn ($row) => $offerValue($row) === $offer->value)->sum('revenue'),
            ], $event->activeOffers()),
            'by_channel' => array_values(array_map(fn (SalesChannel $channel) => [
                'channel' => $channel,
                'passes' => (int) ($channels[$channel->value] ?? 0),
                'share' => (int) round(((int) ($channels[$channel->value] ?? 0)) / $channelTotal * 100),
            ], array_filter(SalesChannel::cases(), fn (SalesChannel $channel) => $channel->isAvailable()))),
            'by_point' => $event->boardingPoints->map(function (BoardingPoint $point) use ($passes, $missions, $offerValue, $event) {
                $rows = $passes->where('boarding_point_id', $point->id);

                return [
                    'point' => $point,
                    'passes' => (int) $rows->sum('passes'),
                    'by_offer' => collect($event->activeOffers())->mapWithKeys(fn (Offer $offer) => [
                        $offer->value => (int) $rows->filter(fn ($row) => $offerValue($row) === $offer->value)->sum('passes'),
                    ])->all(),
                    'buses' => (int) ($missions[$point->id]->buses ?? 0),
                    'revenue' => (int) $rows->sum('revenue'),
                ];
            })->values()->all(),
        ];
    }
}
