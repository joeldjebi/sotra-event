<?php

namespace App\Services\Reporting;

use App\Enums\Direction;
use App\Enums\Offer;
use App\Enums\PassStatus;
use App\Models\Mission;
use App\Models\Pass;

/**
 * Demande par commune pour la direction commerciale : Pass vendus à l'aller et au retour, par offre, recette
 * et places prévues au départ de chaque commune, sur un ou plusieurs événements de la compagnie.
 */
class CommuneDemand
{
    /**
     * @param  list<int>  $eventIds
     * @return array{
     *     rows: list<array{commune: string, zones: string, events: int, outbound: int, return: int, passes: int, by_offer: array<string, int>, revenue: int, seats: int, fill_rate: int|null, share: int}>,
     *     totals: array{outbound: int, return: int, passes: int, revenue: int, seats: int}
     * }
     */
    public function build(array $eventIds): array
    {
        if ($eventIds === []) {
            return ['rows' => [], 'totals' => ['outbound' => 0, 'return' => 0, 'passes' => 0, 'revenue' => 0, 'seats' => 0]];
        }

        $offerColumns = implode(', ', array_map(
            fn (Offer $offer): string => "count(*) filter (where passes.offer = '{$offer->value}') as offer_{$offer->value}",
            Offer::cases(),
        ));

        $sales = Pass::query()->withoutGlobalScopes()
            ->join('boarding_points', 'boarding_points.id', '=', 'passes.boarding_point_id')
            ->whereIn('passes.event_id', $eventIds)
            ->whereIn('passes.status', PassStatus::sold())
            ->groupBy('boarding_points.commune_code')
            ->selectRaw('boarding_points.commune_code, min(boarding_points.commune) as commune, string_agg(distinct boarding_points.zone::text, \', \') as zones, count(distinct passes.event_id) as events')
            ->selectRaw("count(*) filter (where passes.pass_type in ('aller_retour', 'aller_simple')) as outbound, count(*) filter (where passes.pass_type in ('aller_retour', 'retour_simple')) as return_count")
            ->selectRaw("count(*) as passes, coalesce(sum(passes.price), 0) as revenue, {$offerColumns}")
            ->get();

        $seats = Mission::query()->withoutGlobalScopes()
            ->join('boarding_points', 'boarding_points.id', '=', 'missions.boarding_point_id')
            ->whereIn('missions.event_id', $eventIds)
            ->where('missions.direction', Direction::Outbound->value)
            ->whereNull('missions.replaced_by_mission_id')
            ->groupBy('boarding_points.commune_code')
            ->selectRaw('boarding_points.commune_code, coalesce(sum(missions.seats), 0) as seats')
            ->pluck('seats', 'commune_code');

        $total = max(1, (int) $sales->sum('passes'));

        $rows = $sales->map(function ($row) use ($seats, $total): array {
            $outbound = (int) $row->getAttribute('outbound');
            $planned = (int) ($seats[$row->getAttribute('commune_code')] ?? 0);

            return [
                'commune' => (string) $row->getAttribute('commune'),
                'zones' => (string) $row->getAttribute('zones'),
                'events' => (int) $row->getAttribute('events'),
                'outbound' => $outbound,
                'return' => (int) $row->getAttribute('return_count'),
                'passes' => (int) $row->getAttribute('passes'),
                'by_offer' => collect(Offer::cases())->mapWithKeys(fn (Offer $offer): array => [$offer->value => (int) $row->getAttribute('offer_'.$offer->value)])->all(),
                'revenue' => (int) $row->getAttribute('revenue'),
                'seats' => $planned,
                'fill_rate' => $planned > 0 ? (int) round($outbound / $planned * 100) : null,
                'share' => (int) round((int) $row->getAttribute('passes') / $total * 100),
            ];
        })->sortByDesc('passes')->values()->all();

        return [
            'rows' => $rows,
            'totals' => [
                'outbound' => (int) array_sum(array_column($rows, 'outbound')),
                'return' => (int) array_sum(array_column($rows, 'return')),
                'passes' => (int) array_sum(array_column($rows, 'passes')),
                'revenue' => (int) array_sum(array_column($rows, 'revenue')),
                'seats' => (int) array_sum(array_column($rows, 'seats')),
            ],
        ];
    }
}
