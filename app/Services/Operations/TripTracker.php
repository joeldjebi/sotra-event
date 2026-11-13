<?php

namespace App\Services\Operations;

use App\Enums\Direction;
use App\Enums\DropZoneUsage;
use App\Enums\MissionStatus;
use App\Events\MissionUpdated;
use App\Models\Mission;
use App\Models\Position;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Suivi des missions : positions GPS, détection d'arrivée, heure d'arrivée estimée (CV-05, PR-02, SP-18).
 *
 * Un bus aller est « arrivé » quand sa position entre dans le périmètre d'une zone de dépose de l'événement ;
 * un bus retour, quand il entre à moins de 300 m de son point d'embarquement.
 */
class TripTracker
{
    private const RETURN_ARRIVAL_RADIUS_M = 300;

    private const DEFAULT_SPEED_KMH = 30;

    /**
     * @param  list<array{latitude: float, longitude: float, recorded_at: string, accuracy_m?: int|null, speed_kmh?: float|null}>  $points
     */
    public function record(Mission $mission, array $points): int
    {
        $stored = 0;

        foreach ($points as $point) {
            if (! $this->duringMission($mission, Carbon::parse($point['recorded_at']))) {
                continue;
            }

            $mission->positions()->create([
                'recorded_at' => Carbon::parse($point['recorded_at']),
                'location' => Point::makeGeodetic($point['latitude'], $point['longitude']),
                'accuracy_m' => $point['accuracy_m'] ?? null,
                'speed_kmh' => $point['speed_kmh'] ?? null,
            ]);
            $stored++;
        }

        $last = $mission->lastPosition();

        if ($last !== null && $mission->status === MissionStatus::OnRoute && $this->hasArrived($mission, $last)) {
            $mission->update(['status' => MissionStatus::Arrived, 'arrived_at' => $last->recorded_at]);
        }

        MissionUpdated::dispatch($mission->fresh());

        return $stored;
    }

    public function hasArrived(Mission $mission, Position $position): bool
    {
        $wkt = sprintf('SRID=4326;POINT(%F %F)', $position->location->getLongitude(), $position->location->getLatitude());

        if ($mission->direction === Direction::Outbound) {
            return DB::table('drop_zones')
                ->where('event_id', $mission->event_id)
                ->where('usage', DropZoneUsage::DropOff->value)
                ->whereRaw('ST_DWithin(location, ST_GeogFromText(?), radius_m)', [$wkt])
                ->exists();
        }

        return DB::table('boarding_points')
            ->where('id', $mission->boarding_point_id)
            ->whereRaw('ST_DWithin(location, ST_GeogFromText(?), ?)', [$wkt, self::RETURN_ARRIVAL_RADIUS_M])
            ->exists();
    }

    /**
     * NF-12 (loi n° 2013-450) : la position du convoyeur n'est conservée que pendant la mission,
     * de l'embarquement à l'arrivée (une file hors ligne envoyée plus tard reste acceptée pour cette période).
     */
    private function duringMission(Mission $mission, Carbon $recordedAt): bool
    {
        if (in_array($mission->status, [MissionStatus::Planned, MissionStatus::Finished], true)) {
            return false;
        }

        return $mission->arrived_at === null || $recordedAt->lessThanOrEqualTo($mission->arrived_at->copy()->addMinutes(5));
    }

    /**
     * Minutes restantes jusqu'à la destination, à partir de la dernière position (null si inconnue).
     */
    public function etaMinutes(Mission $mission): ?int
    {
        $last = $mission->lastPosition();

        if ($last === null || in_array($mission->status, [MissionStatus::Arrived, MissionStatus::Finished], true)) {
            return null;
        }

        $destination = $this->destination($mission);

        if ($destination === null) {
            return null;
        }

        $meters = (float) DB::scalar(
            'SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)',
            [$last->location->getLongitude(), $last->location->getLatitude(), $destination->getLongitude(), $destination->getLatitude()],
        );
        $speed = max(10.0, (float) ($last->speed_kmh ?? self::DEFAULT_SPEED_KMH));

        return (int) ceil($meters / 1000 / $speed * 60);
    }

    public function destination(Mission $mission): ?Point
    {
        if ($mission->direction === Direction::Return) {
            return $mission->boardingPoint->location;
        }

        $zone = $mission->event->dropZones()->where('usage', DropZoneUsage::DropOff)->first();

        return $zone->location ?? $mission->event->venue->location;
    }
}
