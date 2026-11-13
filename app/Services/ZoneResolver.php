<?php

namespace App\Services;

use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\DB;

/**
 * Zone tarifaire d'un point d'embarquement selon sa distance au lieu de l'événement (RG-06) :
 * Zone 1 < 15 km, Zone 2 de 15 à 25 km, Zone 3 > 25 km (bornes dans config eventtrans.events.zones).
 *
 * La distance est géodésique (à vol d'oiseau, PostGIS). Le calcul routier viendra avec l'outil
 * d'itinéraire ; en attendant, ACCES EVENT peut forcer la zone avec une justification.
 */
class ZoneResolver
{
    public function distanceKm(Point $from, Point $to): float
    {
        $meters = DB::scalar(
            'SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)',
            [$from->getLongitude(), $from->getLatitude(), $to->getLongitude(), $to->getLatitude()],
        );

        return round((float) $meters / 1000, 2);
    }

    public function zoneFor(float $distanceKm): int
    {
        /** @var array<int, int|null> $zones */
        $zones = config('eventtrans.events.zones');

        $first = array_key_first($zones);

        // Zone 1 : strictement moins de 15 km ; zones suivantes : borne haute incluse (« de 15 à 25 km »).
        foreach ($zones as $zone => $maxKm) {
            if ($maxKm === null || ($zone === $first ? $distanceKm < $maxKm : $distanceKm <= $maxKm)) {
                return (int) $zone;
            }
        }

        return (int) array_key_last($zones);
    }

    /**
     * Durée de trajet estimée (minutes) tant que l'itinéraire réel n'est pas calculé.
     */
    public function estimatedTravelMinutes(float $distanceKm): int
    {
        $speed = max(1, (int) config('eventtrans.events.estimated_speed_kmh'));

        return (int) max(10, (int) ceil($distanceKm / $speed * 60));
    }

    /**
     * @return list<int>
     */
    public static function zones(): array
    {
        return array_map('intval', array_keys((array) config('eventtrans.events.zones')));
    }

    public static function zoneLabel(int $zone): string
    {
        /** @var array<int, int|null> $zones */
        $zones = config('eventtrans.events.zones');
        $previous = $zones[$zone - 1] ?? null;
        $max = $zones[$zone] ?? null;

        return match (true) {
            $previous === null && $max !== null => "moins de {$max} km",
            $max === null => "plus de {$previous} km",
            default => "{$previous} à {$max} km",
        };
    }
}
