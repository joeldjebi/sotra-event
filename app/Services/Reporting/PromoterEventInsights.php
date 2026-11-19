<?php

namespace App\Services\Reporting;

use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\ScanReason;
use App\Enums\ScanResult;
use App\Enums\Segment;
use App\Models\BoardingPoint;
use App\Models\Event;
use App\Models\Pass;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Vue détaillée d'un événement pour son promoteur : volumes uniquement (Pass, places, bus, montées),
 * jamais les montants (recette, commission, part compagnie) ni les données nominatives des spectateurs.
 */
class PromoterEventInsights
{
    public function __construct(private readonly EventSalesReport $sales) {}

    /**
     * Statuts visibles par le promoteur : à partir de l'acceptation par la compagnie (les tarifs sont alors fixés).
     *
     * @return list<EventStatus>
     */
    public static function visibleStatuses(): array
    {
        return [EventStatus::Accepted, EventStatus::OnSale, EventStatus::Finished, EventStatus::Cancelled, EventStatus::Postponed];
    }

    /**
     * Pass vendus et capacité ouverte, par événement, pour la liste « Mes événements ».
     *
     * @param  list<int>  $eventIds
     * @return array<int, int>
     */
    public static function soldByEvent(array $eventIds): array
    {
        return Pass::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', PassStatus::sold())
            ->selectRaw('event_id, count(*) as passes')
            ->groupBy('event_id')
            ->pluck('passes', 'event_id')
            ->map(fn ($passes): int => (int) $passes)
            ->all();
    }

    /**
     * @return array{
     *     sold: int, capacity: int, remaining: int, fill_rate: int|null, buses_planned: int,
     *     timeline: list<array{label: string, at: Carbon|null, done: bool}>
     * }
     */
    public function summary(Event $event): array
    {
        $sales = $this->sales->build($event);

        return [
            'sold' => $sales['sold'],
            'capacity' => $sales['capacity'],
            'remaining' => max(0, $sales['capacity'] - $sales['sold']),
            'fill_rate' => $sales['capacity'] > 0 ? (int) round($sales['sold'] / $sales['capacity'] * 100) : null,
            'buses_planned' => $sales['buses_planned'],
            'timeline' => $this->timeline($event),
        ];
    }

    /**
     * @return array{
     *     sold: int,
     *     daily: array{labels: list<string>, series: list<array{name: string, values: list<int>, color: string}>, unit: string},
     *     by_offer: list<array{offer: Offer, passes: int, capacity: int, remaining: int}>,
     *     by_type: list<array{type: PassType, passes: int}>,
     *     by_channel: list<array{label: string, passes: int, share: int}>
     * }
     */
    public function sales(Event $event): array
    {
        $report = $this->sales->build($event);
        $capacities = $event->offers->mapWithKeys(fn ($offer): array => [$offer->offer->value => (int) $offer->capacity()]);

        $byType = Pass::query()
            ->where('event_id', $event->id)
            ->whereIn('status', PassStatus::sold())
            ->selectRaw('pass_type, count(*) as passes')
            ->groupBy('pass_type')
            ->pluck('passes', 'pass_type');

        return [
            'sold' => $report['sold'],
            'daily' => $this->dailyChart($event),
            'by_offer' => array_map(fn (array $row): array => [
                'offer' => $row['offer'],
                'passes' => $row['passes'],
                'capacity' => (int) ($capacities[$row['offer']->value] ?? 0),
                'remaining' => max(0, (int) ($capacities[$row['offer']->value] ?? 0) - $row['passes']),
            ], $report['by_offer']),
            'by_type' => array_map(fn (PassType $type): array => [
                'type' => $type,
                'passes' => (int) ($byType[$type->value] ?? 0),
            ], PassType::cases()),
            'by_channel' => array_map(fn (array $row): array => [
                'label' => $row['channel']->label(),
                'passes' => $row['passes'],
                'share' => $row['share'],
            ], $report['by_channel']),
        ];
    }

    /**
     * Points d'embarquement : horaires, Pass vendus par offre, bus prévus, montées à l'aller et au retour.
     *
     * @return list<array{point: BoardingPoint, passes: int, by_offer: array<string, int>, buses: int, boarded_outbound: int, boarded_return: int}>
     */
    public function points(Event $event): array
    {
        $boarded = $this->boardedPasses($event)
            ->selectRaw('passes.boarding_point_id, scans.segment, count(distinct scans.pass_id) as passes')
            ->groupBy('passes.boarding_point_id', 'scans.segment')
            ->toBase()
            ->get();

        $count = fn (int $pointId, Segment $segment): int => (int) $boarded
            ->first(fn (object $row): bool => (int) $row->boarding_point_id === $pointId && $row->segment === $segment->value)?->passes;

        return array_map(fn (array $row): array => [
            'point' => $row['point'],
            'passes' => $row['passes'],
            'by_offer' => $row['by_offer'],
            'buses' => $row['buses'],
            'boarded_outbound' => $count($row['point']->id, Segment::Outbound),
            'boarded_return' => $count($row['point']->id, Segment::Return),
        ], $this->sales->build($event)['by_point']);
    }

    /**
     * Embarquement : Pass utilisés à l'aller et au retour sur les Pass qui comportent ce segment,
     * et scans refusés par motif.
     *
     * @return array{
     *     outbound: array{boarded: int, expected: int, rate: int|null},
     *     return: array{boarded: int, expected: int, rate: int|null},
     *     scans: int,
     *     refused: int,
     *     refusals: list<array{reason: string, count: int}>
     * }
     */
    public function boarding(Event $event): array
    {
        $types = Pass::query()
            ->where('event_id', $event->id)
            ->whereIn('status', PassStatus::sold())
            ->selectRaw('pass_type, count(*) as passes')
            ->groupBy('pass_type')
            ->pluck('passes', 'pass_type');

        $expectedOutbound = (int) ($types[PassType::RoundTrip->value] ?? 0) + (int) ($types[PassType::Outbound->value] ?? 0);
        $expectedReturn = (int) ($types[PassType::RoundTrip->value] ?? 0) + (int) ($types[PassType::Return->value] ?? 0);

        $boarded = $this->boardedPasses($event)
            ->selectRaw('scans.segment, count(distinct scans.pass_id) as passes')
            ->groupBy('scans.segment')
            ->pluck('passes', 'segment');

        $eventScans = Scan::query()->whereIn('mission_id', DB::table('missions')->where('event_id', $event->id)->select('id'));
        $refusals = (clone $eventScans)
            ->where('result', ScanResult::Red)
            ->selectRaw('reason, count(*) as scans')
            ->groupBy('reason')
            ->orderByDesc('scans')
            ->pluck('scans', 'reason');

        $segment = fn (int $boarded, int $expected): array => [
            'boarded' => $boarded,
            'expected' => $expected,
            'rate' => $expected > 0 ? (int) round($boarded / $expected * 100) : null,
        ];

        return [
            'outbound' => $segment((int) ($boarded[Segment::Outbound->value] ?? 0), $expectedOutbound),
            'return' => $segment((int) ($boarded[Segment::Return->value] ?? 0), $expectedReturn),
            'scans' => (clone $eventScans)->count(),
            'refused' => (int) $refusals->sum(),
            'refusals' => $refusals->map(fn ($count, $reason): array => [
                'reason' => ScanReason::tryFrom((string) $reason)?->label() ?? 'Autre',
                'count' => (int) $count,
            ])->values()->all(),
        ];
    }

    /**
     * Scans acceptés (vert ou orange) des Pass de l'événement, joints à leur Pass.
     *
     * @return Builder<Scan>
     */
    private function boardedPasses(Event $event): Builder
    {
        return Scan::query()
            ->join('passes', 'passes.id', '=', 'scans.pass_id')
            ->where('passes.event_id', $event->id)
            ->whereIn('scans.result', [ScanResult::Green->value, ScanResult::Orange->value])
            ->whereIn('scans.segment', [Segment::Outbound->value, Segment::Return->value]);
    }

    /**
     * Pass vendus par jour, de l'ouverture de la vente à aujourd'hui (ou au jour de l'événement).
     *
     * @return array{labels: list<string>, series: list<array{name: string, values: list<int>, color: string}>, unit: string}
     */
    private function dailyChart(Event $event): array
    {
        $perDay = Pass::query()
            ->where('event_id', $event->id)
            ->whereIn('status', PassStatus::sold())
            ->whereNotNull('issued_at')
            ->selectRaw('date(issued_at) as day, count(*) as passes')
            ->groupBy('day')
            ->pluck('passes', 'day');

        $first = $event->sale_opened_at ?? ($perDay->keys()->min() !== null ? Carbon::parse($perDay->keys()->min()) : null);
        $labels = [];
        $values = [];

        if ($first !== null) {
            $last = now()->min($event->starts_at)->startOfDay();
            $day = $first->copy()->startOfDay()->max($last->copy()->subDays(59));

            for (; $day->lte($last); $day->addDay()) {
                $labels[] = $day->format('d/m');
                $values[] = (int) ($perDay[$day->toDateString()] ?? 0);
            }
        }

        return [
            'labels' => $labels,
            'series' => [['name' => 'Pass vendus', 'values' => $values, 'color' => 'brand']],
            'unit' => 'Pass',
        ];
    }

    /**
     * @return list<array{label: string, at: Carbon|null, done: bool}>
     */
    private function timeline(Event $event): array
    {
        $steps = [
            ['label' => 'Proposé à la compagnie', 'at' => $event->proposed_at],
            ['label' => 'Accepté par la compagnie', 'at' => $event->accepted_at],
            ['label' => 'Vente ouverte', 'at' => $event->sale_opened_at],
        ];

        if ($event->postponed_at !== null) {
            $steps[] = ['label' => 'Reporté (initialement le '.$event->original_starts_at?->format('d/m/Y').')', 'at' => $event->postponed_at];
        }

        if ($event->cancelled_at !== null) {
            $steps[] = ['label' => 'Annulé', 'at' => $event->cancelled_at];
        } else {
            $steps[] = ['label' => 'Jour de l\'événement', 'at' => $event->starts_at];
        }

        return array_map(fn (array $step): array => $step + ['done' => $step['at'] !== null && $step['at']->isPast()], $steps);
    }
}
