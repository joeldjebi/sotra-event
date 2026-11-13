<?php

namespace App\Services\Reporting;

use App\Enums\AlertStatus;
use App\Enums\Direction;
use App\Enums\MissionStatus;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\ScanResult;
use App\Enums\Segment;
use App\Models\BreakdownAlert;
use App\Models\Event;
use App\Models\Mission;
use App\Models\Pass;
use App\Models\Scan;
use App\Services\Operations\TripTracker;
use App\Services\ZoneResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Suivi des arrivées au lieu de l'événement pour le promoteur (PR-01 à PR-03, PR-09).
 *
 * Un bus est « arrivé » quand il entre dans la zone de dépose ou que le convoyeur confirme l'arrivée ;
 * ses passagers scannés comptent alors comme spectateurs arrivés. Le flux prévu = départ prévu + durée
 * de trajet estimée du point (saisie, ou à défaut estimée d'après la distance).
 */
class ArrivalTracking
{
    private const int SLOT_MINUTES = 15;

    /** @var Collection<int, Mission>|null */
    private ?Collection $missions = null;

    public function __construct(
        public readonly Event $event,
        private readonly TripTracker $tracker,
        private readonly ZoneResolver $zones,
    ) {}

    /**
     * PR-02 : spectateurs arrivés, bus arrivés / prévus / en route, prochaine arrivée, retards.
     *
     * @return array{arrived: int, sold: int, buses_arrived: int, buses_planned: int, buses_on_route: int, next: array{at: Carbon, bus: string, point: string, passengers: int}|null, delays: list<array{bus: string, point: string, label: string, action: string|null}>}
     */
    public function summary(): array
    {
        $outbound = $this->outboundMissions();
        $arrivedIds = $outbound->filter(fn (Mission $mission) => $mission->arrived_at !== null)->modelKeys();

        $next = $outbound->where('status', MissionStatus::OnRoute)
            ->map(fn (Mission $mission) => ['mission' => $mission, 'eta' => $this->tracker->etaMinutes($mission)])
            ->filter(fn (array $row) => $row['eta'] !== null)
            ->sortBy('eta')
            ->first();

        return [
            'arrived' => $arrivedIds === [] ? 0 : $this->boardedOn($arrivedIds)->distinct()->count('scans.pass_id'),
            'sold' => Pass::query()->where('event_id', $this->event->id)->whereIn('status', PassStatus::sold())
                ->whereIn('pass_type', [PassType::RoundTrip, PassType::Outbound])->count(),
            'buses_arrived' => count($arrivedIds),
            'buses_planned' => $outbound->count(),
            'buses_on_route' => $outbound->where('status', MissionStatus::OnRoute)->count(),
            'next' => $next === null ? null : [
                'at' => now()->addMinutes($next['eta']),
                'bus' => $next['mission']->bus->number ?? '—',
                'point' => $next['mission']->boardingPoint->commune,
                'passengers' => $next['mission']->onBoardCount(),
            ],
            'delays' => $this->delays($outbound),
        ];
    }

    /**
     * PR-03 : spectateurs arrivés par tranche de 15 minutes, prévu et réel, au format de <x-ui.chart>.
     *
     * @return array{labels: list<string>, series: list<array{name: string, values: list<int>, color: string}>, unit: string}
     */
    public function flow(): array
    {
        $planned = [];
        $actual = [];
        $outbound = $this->outboundMissions();
        $passengers = Pass::query()->whereIn('outbound_mission_id', $outbound->modelKeys())->whereIn('status', PassStatus::sold())
            ->selectRaw('outbound_mission_id, count(*) as total')->groupBy('outbound_mission_id')->pluck('total', 'outbound_mission_id');

        foreach ($outbound as $mission) {
            $minutes = $mission->boardingPoint->travel_minutes ?? $this->zones->estimatedTravelMinutes((float) $mission->boardingPoint->distance_km);
            $slot = $this->slot($mission->planned_departure_at->copy()->addMinutes($minutes));
            $planned[$slot] = ($planned[$slot] ?? 0) + (int) ($passengers[$mission->id] ?? 0);

            if ($mission->arrived_at !== null) {
                $slot = $this->slot($mission->arrived_at);
                $actual[$slot] = ($actual[$slot] ?? 0) + $this->boardedOn([$mission->id])->distinct()->count('scans.pass_id');
            }
        }

        $slots = array_merge(array_keys($planned), array_keys($actual));
        sort($slots);
        $labels = [];

        if ($slots !== []) {
            for ($time = (int) $slots[0]; $time <= (int) end($slots); $time += self::SLOT_MINUTES * 60) {
                $labels[] = $time;
            }
        }

        return [
            'labels' => array_map(fn (int $time): string => Carbon::createFromTimestamp($time, config('app.timezone'))->format('H\hi'), $labels),
            'series' => [
                ['name' => 'Prévu', 'values' => array_map(fn (int $time): int => $planned[$time] ?? 0, $labels), 'color' => 'ink-tertiary'],
                ['name' => 'Réel', 'values' => array_map(fn (int $time): int => $actual[$time] ?? 0, $labels), 'color' => 'brand'],
            ],
            'unit' => 'spectateurs',
        ];
    }

    /**
     * PR-09 : bilan transporté aller et retour.
     *
     * @return array{outbound: int, return: int}
     */
    public function transported(): array
    {
        $scans = Scan::query()->join('missions', 'missions.id', '=', 'scans.mission_id')
            ->where('missions.event_id', $this->event->id)
            ->whereIn('scans.result', [ScanResult::Green, ScanResult::Orange])->where('scans.conflict', false);

        return [
            'outbound' => (clone $scans)->where('scans.segment', Segment::Outbound)->distinct()->count('scans.pass_id'),
            'return' => (clone $scans)->where('scans.segment', Segment::Return)->distinct()->count('scans.pass_id'),
        ];
    }

    /**
     * Missions aller encore actives (un bus remplacé laisse place à sa mission de remplacement).
     *
     * @return Collection<int, Mission>
     */
    public function outboundMissions(): Collection
    {
        return $this->missions ??= Mission::query()->withoutGlobalScopes()
            ->with(['bus', 'boardingPoint', 'event.venue', 'event.dropZones'])
            ->where('event_id', $this->event->id)
            ->where('direction', Direction::Outbound)
            ->whereNull('replaced_by_mission_id')
            ->orderBy('planned_departure_at')
            ->get();
    }

    /**
     * @param  Collection<int, Mission>  $missions
     * @return list<array{bus: string, point: string, label: string, action: string|null}>
     */
    private function delays(Collection $missions): array
    {
        $tolerance = (int) config('eventtrans.quality.on_time_tolerance_minutes');
        $alerts = BreakdownAlert::query()->withoutGlobalScopes()->with(['bus', 'mission.boardingPoint', 'techTeam', 'mission.replacedBy.bus'])
            ->where('event_id', $this->event->id)->whereIn('status', [AlertStatus::Open, AlertStatus::Taken])->get();
        $rows = [];

        foreach ($alerts as $alert) {
            $rows[] = [
                'bus' => 'Bus '.($alert->bus->number ?? '?'),
                'point' => $alert->mission->boardingPoint->commune,
                'label' => 'Panne · '.$alert->type->label(),
                'action' => match (true) {
                    $alert->mission->replacedBy !== null => 'Bus '.($alert->mission->replacedBy->bus->number ?? '?').' en remplacement',
                    $alert->techTeam !== null => "Équipe {$alert->techTeam->code} en intervention",
                    default => 'Service technique prévenu',
                },
            ];
        }

        foreach ($missions as $mission) {
            if ($mission->arrived_at !== null || $mission->status === MissionStatus::Incident) {
                continue;
            }

            $gap = (int) floor($mission->planned_departure_at->diffInMinutes($mission->actual_departure_at ?? now(), false));

            if ($gap > $tolerance) {
                $rows[] = [
                    'bus' => $mission->bus ? "Bus {$mission->bus->number}" : 'Bus non affecté',
                    'point' => $mission->boardingPoint->commune,
                    'label' => $mission->actual_departure_at ? "Parti avec {$gap} min de retard" : "Départ attendu depuis {$gap} min",
                    'action' => null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<int>  $missionIds
     * @return Builder<Scan>
     */
    private function boardedOn(array $missionIds)
    {
        return Scan::query()
            ->whereIn('scans.mission_id', $missionIds)
            ->whereIn('scans.result', [ScanResult::Green, ScanResult::Orange])
            ->whereIn('scans.segment', [Segment::Outbound, Segment::Control])
            ->where('scans.conflict', false);
    }

    private function slot(Carbon $time): int
    {
        $timestamp = $time->getTimestamp();

        return $timestamp - ($timestamp % (self::SLOT_MINUTES * 60));
    }
}
