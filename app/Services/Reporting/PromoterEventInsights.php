<?php

namespace App\Services\Reporting;

use App\Enums\AlertStatus;
use App\Enums\BreakdownUrgency;
use App\Enums\ComplaintStatus;
use App\Enums\Direction;
use App\Enums\EventStatus;
use App\Enums\MissionStatus;
use App\Enums\Offer;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\RefundStatus;
use App\Enums\ScanReason;
use App\Enums\ScanResult;
use App\Enums\Segment;
use App\Models\BoardingPoint;
use App\Models\BreakdownAlert;
use App\Models\Complaint;
use App\Models\Event;
use App\Models\Mission;
use App\Models\Pass;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Scan;
use App\Services\Operations\TripTracker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Vue détaillée d'un événement pour son promoteur : volumes uniquement (Pass, places, bus, montées),
 * jamais les montants (recette, commission, part compagnie) ni les données nominatives des spectateurs.
 */
class PromoterEventInsights
{
    public function __construct(
        private readonly EventSalesReport $sales,
        private readonly TripTracker $tracker,
    ) {}

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
     * Transport : chaque départ (bus, point, offre), passagers montés, horaires prévu et réel, écart, arrivée.
     *
     * @return array{
     *     departed: int, on_time_rate: int|null, arrived: int, planned: int,
     *     rows: list<array{direction: Direction, point: string, offer: Offer, bus: string|null, capacity: int, boarded: int, planned_at: Carbon, actual_at: Carbon|null, gap_label: string, on_time: bool|null, arrived_at: Carbon|null, status: MissionStatus, replaced: bool}>
     * }
     */
    public function transport(Event $event): array
    {
        $quality = new QualityMetrics($event);
        $summary = $quality->summary();
        $missions = $quality->missions();

        $boarded = Scan::query()
            ->whereIn('mission_id', $missions->pluck('id'))
            ->whereIn('result', [ScanResult::Green->value, ScanResult::Orange->value])
            ->where('conflict', false)
            ->selectRaw('mission_id, count(*) as passengers')
            ->groupBy('mission_id')
            ->toBase()
            ->pluck('passengers', 'mission_id');

        return [
            'planned' => $missions->whereNull('replaced_by_mission_id')->count(),
            'departed' => $summary['departed'],
            'on_time_rate' => $summary['on_time_rate'],
            'arrived' => $missions->whereNotNull('arrived_at')->count(),
            'rows' => $missions->map(function (Mission $mission) use ($boarded): array {
                $gap = QualityMetrics::gap($mission);

                return [
                    'direction' => $mission->direction,
                    'point' => $mission->boardingPoint->commune,
                    'offer' => $mission->offer,
                    'bus' => $mission->bus?->number,
                    'capacity' => $mission->capacity(),
                    'boarded' => (int) ($boarded[$mission->id] ?? 0),
                    'planned_at' => $mission->planned_departure_at,
                    'actual_at' => $mission->actual_departure_at,
                    'gap_label' => QualityMetrics::gapLabel($gap),
                    'on_time' => $gap === null ? null : $gap <= QualityMetrics::tolerance(),
                    'arrived_at' => $mission->arrived_at,
                    'status' => $mission->status,
                    'replaced' => $mission->replaced_by_mission_id !== null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * Incidents : pannes signalées, passagers à bord, prise en charge et issue de l'intervention.
     *
     * @return list<array{at: Carbon, bus: string|null, point: string|null, type: string, urgent: bool, passengers: int, place: string|null, status: AlertStatus, result: string|null, duration: int|null}>
     */
    public function incidents(Event $event): array
    {
        return (new QualityMetrics($event))->breakdowns()->map(fn (BreakdownAlert $alert): array => [
            'at' => $alert->created_at,
            'bus' => $alert->bus?->number,
            'point' => $alert->mission->boardingPoint->commune,
            'type' => $alert->type->label(),
            'urgent' => $alert->urgency === BreakdownUrgency::Urgent,
            'passengers' => (int) $alert->passengers_on_board,
            'place' => $alert->place_label,
            'status' => $alert->status,
            'result' => $alert->intervention?->result?->label(),
            'duration' => $alert->closed_at === null ? null : (int) $alert->created_at->diffInMinutes($alert->closed_at),
        ])->values()->all();
    }

    /**
     * Avis : note moyenne, notes par critère et commentaires, sans l'identité des spectateurs.
     *
     * @return array{rating: float|null, reviews: int, criteria: array<string, array{label: string, average: float|null}>, comments: list<array{at: Carbon, rating: float, comment: string, point: string|null}>}
     */
    public function reviews(Event $event): array
    {
        $quality = new QualityMetrics($event);
        $summary = $quality->summary();

        return [
            'rating' => $summary['rating'],
            'reviews' => $summary['reviews'],
            'criteria' => $quality->criteria(),
            'comments' => Review::query()
                ->with('mission.boardingPoint')
                ->where('event_id', $event->id)
                ->whereNotNull('comment')
                ->where('comment', '<>', '')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (Review $review): array => [
                    'at' => $review->created_at,
                    'rating' => round($review->average(), 1),
                    'comment' => (string) $review->comment,
                    'point' => $review->mission?->boardingPoint?->commune,
                ])->all(),
        ];
    }

    /**
     * Réclamations : volumes par statut et par motif, et liste sans l'identité ni le détail rédigé par le spectateur.
     *
     * @return array{open: int, in_progress: int, resolved: int, by_category: list<array{label: string, count: int}>, rows: list<array{number: string, at: Carbon, category: string, title: string, point: string|null, collective: bool, affected: int, status: ComplaintStatus}>}
     */
    public function complaints(Event $event): array
    {
        $complaints = Complaint::query()->with('mission.boardingPoint')->where('event_id', $event->id)->latest()->get();

        return [
            'open' => $complaints->where('status', ComplaintStatus::Open)->count(),
            'in_progress' => $complaints->where('status', ComplaintStatus::InProgress)->count(),
            'resolved' => $complaints->where('status', ComplaintStatus::Resolved)->count(),
            'by_category' => $complaints->groupBy(fn (Complaint $complaint): string => $complaint->category->label())
                ->map(fn ($group, $label): array => ['label' => (string) $label, 'count' => $group->count()])
                ->sortByDesc('count')->values()->all(),
            'rows' => $complaints->map(fn (Complaint $complaint): array => [
                'number' => $complaint->number,
                'at' => $complaint->created_at,
                'category' => $complaint->category->label(),
                'title' => $complaint->title,
                'point' => $complaint->mission?->boardingPoint?->commune,
                'collective' => $complaint->is_collective,
                'affected' => (int) $complaint->affected_count,
                'status' => $complaint->status,
            ])->all(),
        ];
    }

    /**
     * Annulations et remboursements, en nombre de Pass (jamais en montant).
     *
     * @return array{cancelled: int, refunded: int, by_reason: list<array{label: string, count: int}>, pending: int}
     */
    public function cancellations(Event $event): array
    {
        $statuses = Pass::query()->where('event_id', $event->id)
            ->whereIn('status', [PassStatus::Cancelled, PassStatus::Refunded])
            ->selectRaw('status, count(*) as passes')->groupBy('status')
            ->toBase()->pluck('passes', 'status');

        $refunds = Refund::query()->where('event_id', $event->id)->get(['reason', 'status']);

        return [
            'cancelled' => (int) ($statuses[PassStatus::Cancelled->value] ?? 0),
            'refunded' => (int) ($statuses[PassStatus::Refunded->value] ?? 0),
            'pending' => $refunds->where('status', RefundStatus::Pending)->count(),
            'by_reason' => $refunds->groupBy(fn (Refund $refund): string => $refund->reason->label())
                ->map(fn ($group, $label): array => ['label' => (string) $label, 'count' => $group->count()])
                ->sortByDesc('count')->values()->all(),
        ];
    }

    /**
     * Carte en direct : dernière position des bus en mission (embarquement, en route, panne, arrivés),
     * le lieu de l'événement et les zones de dépose et de reprise ; heure d'arrivée estimée par bus.
     *
     * @return array{
     *     map: array{center: list<float>, zoom: int, fit: bool, markers: list<array{id: int, lat: float, lng: float, label: string, tone: string}>, zones: list<array{lat: float, lng: float, radius: int, label: string}>},
     *     buses: list<array{label: string, direction: Direction, point: string, status: MissionStatus, passengers: int, eta: int|null, seen_at: Carbon|null}>,
     *     tracked: int
     * }
     */
    public function liveMap(Event $event): array
    {
        $event->loadMissing(['venue', 'dropZones']);

        $missions = Mission::query()
            ->with(['bus', 'boardingPoint'])
            ->where('event_id', $event->id)
            ->whereNull('replaced_by_mission_id')
            ->whereIn('status', [MissionStatus::Boarding, MissionStatus::OnRoute, MissionStatus::Incident, MissionStatus::Arrived])
            ->orderBy('direction')->orderBy('planned_departure_at')
            ->get();

        $buses = $missions->map(function (Mission $mission): array {
            $position = $mission->lastPosition();

            return [
                'mission' => $mission,
                'position' => $position,
                'label' => $mission->bus !== null ? 'Bus '.$mission->bus->number : $mission->boardingPoint->commune,
                'eta' => $this->tracker->etaMinutes($mission),
            ];
        });

        $venue = $event->venue;
        $markers = [[
            'id' => 0, 'lat' => $venue->location->getLatitude(), 'lng' => $venue->location->getLongitude(), 'label' => $venue->name, 'tone' => 'neutral',
        ]];

        foreach ($buses as $bus) {
            if ($bus['position'] === null) {
                continue;
            }

            $markers[] = [
                'id' => $bus['mission']->id,
                'lat' => $bus['position']->location->getLatitude(),
                'lng' => $bus['position']->location->getLongitude(),
                'label' => $bus['label'].' · '.$bus['mission']->status->label(),
                'tone' => match ($bus['mission']->status) {
                    MissionStatus::Incident => 'danger',
                    MissionStatus::Boarding => 'warning',
                    default => 'success',
                },
            ];
        }

        return [
            'map' => [
                'center' => [$venue->location->getLatitude(), $venue->location->getLongitude()],
                'zoom' => 11,
                'fit' => true,
                'markers' => $markers,
                'zones' => $event->dropZones->map(fn ($zone): array => [
                    'lat' => $zone->location->getLatitude(),
                    'lng' => $zone->location->getLongitude(),
                    'radius' => (int) $zone->radius_m,
                    'label' => $zone->name.' · '.$zone->usage->label(),
                ])->values()->all(),
            ],
            'buses' => $buses->map(fn (array $bus): array => [
                'label' => $bus['label'],
                'direction' => $bus['mission']->direction,
                'point' => $bus['mission']->boardingPoint->commune,
                'status' => $bus['mission']->status,
                'passengers' => $bus['mission']->onBoardCount(),
                'eta' => $bus['eta'],
                'seen_at' => $bus['position']?->recorded_at,
            ])->values()->all(),
            // Le premier repère est le lieu de l'événement.
            'tracked' => count($markers) - 1,
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
