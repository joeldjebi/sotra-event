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
 * - lignes applicables : celles qui vont au lieu de l'événement et celles sans destination précise ;
 * - acceptation d'office quand la compagnie a au moins une ligne applicable ;
 * - capacité par offre = somme des bus des lignes reprises comme points d'embarquement × places par bus (RG-08) ;
 * - points d'embarquement repris des lignes (un point par ligne).
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
        return $event->company->lines()->serving($event->venue_id)->orderBy('commune_code')->orderBy('place')->get();
    }

    public function acceptsAutomatically(Event $event): bool
    {
        return $event->company->lines()->serving($event->venue_id)->exists();
    }

    /**
     * Capacité par défaut d'une offre : bus des lignes dont sont issus les points d'embarquement de l'événement.
     *
     * @return array{bus_count: int, seats_per_bus: int}|null
     */
    public function capacityFor(Event $event, Offer $offer): ?array
    {
        $lineIds = $event->boardingPoints->pluck('company_line_id')->filter()->unique();
        $busCount = $lineIds->isEmpty() ? 0 : (int) $event->company->lines()->whereKey($lineIds)->get()
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
     * Crée un point d'embarquement pour chaque ligne applicable pas encore reprise. Horaires proposés :
     * aller = début − trajet − marge (au quart d'heure), retour = fin prévue + délai ; modifiables ensuite.
     *
     * @return int nombre de points créés
     */
    public function importBoardingPoints(Event $event): int
    {
        $existing = $event->boardingPoints()->pluck('company_line_id');
        $position = (int) $event->boardingPoints()->max('position');
        $created = 0;

        foreach ($this->linesFor($event) as $line) {
            if ($existing->contains($line->id)) {
                continue;
            }

            $distance = $this->zones->distanceKm($event->venue->location, $line->location);
            $travelMinutes = $this->zones->estimatedTravelMinutes($distance);
            $departureAt = $this->roundDownToQuarter($event->starts_at->copy()->subMinutes($travelMinutes + self::ARRIVAL_MARGIN_MINUTES));

            BoardingPoint::query()->create([
                'event_id' => $event->id,
                'company_line_id' => $line->id,
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
