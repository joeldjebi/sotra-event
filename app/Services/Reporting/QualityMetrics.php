<?php

namespace App\Services\Reporting;

use App\Enums\Direction;
use App\Enums\PassStatus;
use App\Enums\ScanReason;
use App\Enums\ScanResult;
use App\Enums\Segment;
use App\Models\BreakdownAlert;
use App\Models\Complaint;
use App\Models\Event;
use App\Models\Mission;
use App\Models\Pass;
use App\Models\Review;
use App\Models\Scan;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Indicateurs de qualité de service d'un événement (§ 8.2 CDC, bilan J+1) :
 * - à l'heure : départ confirmé (CV-14) au plus 10 min après l'heure prévue ;
 * - note d'un chauffeur ou d'un convoyeur : moyenne des avis des passagers de ses bus ;
 * - Pass refusés : scans rouges et scans orange « autre bus ».
 */
class QualityMetrics
{
    /** @var Collection<int, Mission>|null */
    private ?Collection $missions = null;

    /** @var Collection<int, Review>|null */
    private ?Collection $reviews = null;

    public function __construct(public readonly Event $event) {}

    public static function tolerance(): int
    {
        return (int) config('eventtrans.quality.on_time_tolerance_minutes');
    }

    /**
     * Écart au départ en minutes (négatif = en avance), ou null si le départ n'est pas confirmé.
     */
    public static function gap(Mission $mission): ?int
    {
        return $mission->actual_departure_at === null
            ? null
            : (int) round($mission->planned_departure_at->diffInSeconds($mission->actual_departure_at, false) / 60);
    }

    public static function gapLabel(?int $gap): string
    {
        return match (true) {
            $gap === null => 'non parti',
            $gap <= 0 => 'à l\'heure',
            default => "+{$gap} min",
        };
    }

    /**
     * QU-01 : note, départs à l'heure, Pass scannés, Pass refusés.
     *
     * @return array{rating: float|null, reviews: int, response_rate: int, departed: int, on_time: int, on_time_rate: int|null, sold_outbound: int, sold_return: int, scanned_outbound: int, scanned_return: int, refused: int, refused_red: int, refused_wrong_bus: int, complaints_open: int, complaints_resolved: int, breakdowns: int}
     */
    public function summary(): array
    {
        $reviews = $this->reviews();
        $departed = $this->missions()->filter(fn (Mission $mission) => $mission->actual_departure_at !== null && $mission->replaced_by_mission_id === null);
        $onTime = $departed->filter(fn (Mission $mission) => self::gap($mission) <= self::tolerance())->count();

        $sold = Pass::query()->where('event_id', $this->event->id)->whereIn('status', PassStatus::sold())
            ->selectRaw("count(*) filter (where pass_type in ('aller_retour', 'aller_simple')) as outbound, count(*) filter (where pass_type in ('aller_retour', 'retour_simple')) as return")
            ->first();

        $scans = $this->scans();
        $scanned = (clone $scans)->whereIn('scans.result', [ScanResult::Green, ScanResult::Orange])->where('scans.conflict', false)
            ->selectRaw("count(distinct scans.pass_id) filter (where scans.segment = 'aller') as outbound, count(distinct scans.pass_id) filter (where scans.segment = 'retour') as return, count(distinct scans.pass_id) as total")
            ->first();
        $refusedRed = (clone $scans)->where('scans.result', ScanResult::Red)->count();
        $wrongBus = (clone $scans)->where('scans.result', ScanResult::Orange)->where('scans.reason', ScanReason::OtherBus)->count();
        $scannedTotal = (int) $scanned?->getAttribute('total');

        $complaints = Complaint::query()->where('event_id', $this->event->id)
            ->selectRaw("count(*) filter (where status <> 'traitee') as open, count(*) filter (where status = 'traitee') as resolved")->first();

        return [
            'rating' => $reviews->isEmpty() ? null : round($reviews->avg(fn (Review $review) => $review->average()), 1),
            'reviews' => $reviews->count(),
            'response_rate' => $scannedTotal > 0 ? (int) round($reviews->count() / $scannedTotal * 100) : 0,
            'departed' => $departed->count(),
            'on_time' => $onTime,
            'on_time_rate' => $departed->isEmpty() ? null : (int) round($onTime / $departed->count() * 100),
            'sold_outbound' => (int) $sold?->getAttribute('outbound'),
            'sold_return' => (int) $sold?->getAttribute('return'),
            'scanned_outbound' => (int) $scanned?->getAttribute('outbound'),
            'scanned_return' => (int) $scanned?->getAttribute('return'),
            'refused' => $refusedRed + $wrongBus,
            'refused_red' => $refusedRed,
            'refused_wrong_bus' => $wrongBus,
            'complaints_open' => (int) $complaints?->getAttribute('open'),
            'complaints_resolved' => (int) $complaints?->getAttribute('resolved'),
            'breakdowns' => BreakdownAlert::query()->where('event_id', $this->event->id)->count(),
        ];
    }

    /**
     * QU-02 : moyenne par critère.
     *
     * @return array<string, array{label: string, average: float|null}>
     */
    public function criteria(): array
    {
        $reviews = $this->reviews();
        $criteria = [];

        foreach (Review::CRITERIA as $key => $label) {
            $criteria[$key] = ['label' => $label, 'average' => $reviews->isEmpty() ? null : round((float) $reviews->avg($key), 1)];
        }

        return $criteria;
    }

    /**
     * QU-04 : équipages des départs aller (bus, chauffeur, convoyeur et leurs notes, départ réel et écart).
     *
     * @return list<array{mission: Mission, gap: int|null, gap_label: string, on_time: bool|null, driver_rating: float|null, conveyor_rating: float|null, reviews: int}>
     */
    public function crews(): array
    {
        $byMission = $this->reviews()->groupBy('mission_id');
        $staffRating = function (?Staff $staff, string $column) use ($byMission): ?float {
            if ($staff === null) {
                return null;
            }

            $reviews = $this->missions()->where($column, $staff->id)->flatMap(fn (Mission $mission) => $byMission->get($mission->id, collect()));

            return $reviews->isEmpty() ? null : round($reviews->avg(fn (Review $review) => $review->average()), 1);
        };

        return $this->missions()
            ->filter(fn (Mission $mission) => $mission->direction === Direction::Outbound && $mission->bus_id !== null)
            ->map(function (Mission $mission) use ($byMission, $staffRating) {
                $gap = self::gap($mission);

                return [
                    'mission' => $mission,
                    'gap' => $gap,
                    'gap_label' => self::gapLabel($gap),
                    'on_time' => $gap === null ? null : $gap <= self::tolerance(),
                    'driver_rating' => $staffRating($mission->driver, 'driver_id'),
                    'conveyor_rating' => $staffRating($mission->conveyor, 'conveyor_id'),
                    'reviews' => $byMission->get($mission->id, collect())->count(),
                ];
            })->values()->all();
    }

    /**
     * QU-06 : ponctualité par départ, aller et retour.
     *
     * @return array<string, list<array{mission: Mission, gap: int|null, gap_label: string, on_time: bool|null}>>
     */
    public function punctuality(): array
    {
        $rows = ['aller' => [], 'retour' => []];

        foreach ($this->missions() as $mission) {
            $gap = self::gap($mission);
            $rows[$mission->direction->value][] = [
                'mission' => $mission,
                'gap' => $gap,
                'gap_label' => self::gapLabel($gap),
                'on_time' => $gap === null ? null : $gap <= self::tolerance(),
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, BreakdownAlert>
     */
    public function breakdowns(): Collection
    {
        return BreakdownAlert::query()->with(['bus', 'mission.boardingPoint', 'intervention'])
            ->where('event_id', $this->event->id)->orderBy('created_at')->get();
    }

    /**
     * QU-07 : comparaison des événements pilotes (une ligne par événement).
     *
     * @param  iterable<Event>  $events
     * @return list<array{event: Event, sold: int, scanned_rate: int, rating: float|null, reviews: int, on_time_rate: int|null, complaints: int, breakdowns: int}>
     */
    public static function compare(iterable $events): array
    {
        $rows = [];

        foreach ($events as $event) {
            $summary = (new self($event))->summary();
            $sold = $summary['sold_outbound'];
            $rows[] = [
                'event' => $event,
                'sold' => $sold,
                'scanned_rate' => $sold > 0 ? (int) round($summary['scanned_outbound'] / $sold * 100) : 0,
                'rating' => $summary['rating'],
                'reviews' => $summary['reviews'],
                'on_time_rate' => $summary['on_time_rate'],
                'complaints' => $summary['complaints_open'] + $summary['complaints_resolved'],
                'breakdowns' => $summary['breakdowns'],
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, Mission>
     */
    public function missions(): Collection
    {
        return $this->missions ??= Mission::query()
            ->with(['bus', 'driver', 'conveyor', 'boardingPoint'])
            ->where('event_id', $this->event->id)
            ->orderBy('direction')->orderBy('planned_departure_at')->orderBy('boarding_point_id')->orderBy('sequence')
            ->get();
    }

    /**
     * @return Collection<int, Review>
     */
    public function reviews(): Collection
    {
        return $this->reviews ??= Review::query()->where('event_id', $this->event->id)->get();
    }

    /**
     * @return Builder<Scan>
     */
    private function scans(): Builder
    {
        return Scan::query()->join('missions', 'missions.id', '=', 'scans.mission_id')
            ->where('missions.event_id', $this->event->id)
            ->whereIn('scans.segment', [Segment::Outbound, Segment::Return]);
    }

    public static function at(?Carbon $time): string
    {
        return $time?->format('H\hi') ?? '—';
    }

    /**
     * Notation lisible d'une note sur 5 (« 4,3 »).
     */
    public static function rating(?float $rating): string
    {
        return $rating === null ? '—' : number_format($rating, 1, ',', ' ');
    }
}
