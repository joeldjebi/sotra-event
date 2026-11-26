<?php

namespace App\Services;

use App\Enums\Offer;
use App\Models\BoardingPoint;
use App\Models\CompanyLine;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lignes par défaut d'une compagnie appliquées à un événement :
 * - acceptation d'office quand la compagnie a défini ses lignes ;
 * - capacité par offre = somme des bus des communes desservies × places par bus de l'offre (RG-08) ;
 * - points d'embarquement repris des lieux habituels de la compagnie.
 */
class CompanyDefaultLines
{
    /** Marge avant le début de l'événement, en plus de la durée de trajet, pour fixer le départ aller. */
    public const ARRIVAL_MARGIN_MINUTES = 30;

    /** Départ retour après la fin prévue de l'événement. */
    public const RETURN_DELAY_MINUTES = 30;

    public function __construct(private readonly ZoneResolver $zones) {}

    /**
     * @return Collection<int, CompanyLine>
     */
    public function linesFor(Event $event): Collection
    {
        return $event->company->lines()->orderBy('commune_code')->get();
    }

    public function acceptsAutomatically(Event $event): bool
    {
        return $event->company->lines()->exists();
    }

    /**
     * Capacité par défaut d'une offre sur les communes où l'événement a un point d'embarquement.
     *
     * @return array{bus_count: int, seats_per_bus: int}|null
     */
    public function capacityFor(Event $event, Offer $offer): ?array
    {
        $communes = $event->boardingPoints->pluck('commune_code')->unique();
        $busCount = $this->linesFor($event)
            ->filter(fn (CompanyLine $line) => $communes->contains($line->commune_code))
            ->sum(fn (CompanyLine $line) => $line->busesFor($offer));

        return $busCount > 0 ? ['bus_count' => $busCount, 'seats_per_bus' => $event->company->seatsPerBus($offer)] : null;
    }

    /**
     * Renseigne la capacité des offres encore vides ; celle déjà saisie par la compagnie est conservée.
     */
    public function applyCapacity(Event $event): void
    {
        $event->loadMissing(['offers', 'boardingPoints']);

        foreach ($event->offers as $eventOffer) {
            if ($eventOffer->capacity() !== null) {
                continue;
            }

            $capacity = $this->capacityFor($event, $eventOffer->offer);

            if ($capacity !== null) {
                $eventOffer->update($capacity);
            }
        }
    }

    /**
     * Crée un point d'embarquement pour chaque ligne dont la commune n'en a pas encore. Horaires proposés :
     * aller = début − trajet − marge (au quart d'heure), retour = fin prévue + délai ; modifiables ensuite.
     *
     * @return int nombre de points créés
     */
    public function importBoardingPoints(Event $event): int
    {
        $existing = $event->boardingPoints()->pluck('commune_code');
        $position = (int) $event->boardingPoints()->max('position');
        $created = 0;

        foreach ($this->linesFor($event) as $line) {
            if ($existing->contains($line->commune_code)) {
                continue;
            }

            $distance = $this->zones->distanceKm($event->venue->location, $line->location);
            $travelMinutes = $this->zones->estimatedTravelMinutes($distance);
            $departureAt = $this->roundDownToQuarter($event->starts_at->copy()->subMinutes($travelMinutes + self::ARRIVAL_MARGIN_MINUTES));

            BoardingPoint::query()->create([
                'event_id' => $event->id,
                'commune' => $line->communeName(),
                'commune_code' => $line->commune_code,
                'place' => $line->place,
                'location' => $line->location,
                'distance_km' => $distance,
                'zone' => $this->zones->zoneFor($distance),
                'zone_forced' => false,
                'departure_at' => $departureAt,
                'return_departure_at' => $event->ends_at->copy()->addMinutes(self::RETURN_DELAY_MINUTES),
                'travel_minutes' => $travelMinutes,
                'position' => ++$position,
            ]);
            $created++;
        }

        return $created;
    }

    private function roundDownToQuarter(Carbon $time): Carbon
    {
        return $time->setTime($time->hour, intdiv($time->minute, 15) * 15);
    }
}
