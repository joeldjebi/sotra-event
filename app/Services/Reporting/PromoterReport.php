<?php

namespace App\Services\Reporting;

use App\Models\Event;
use App\Services\Operations\TripTracker;
use App\Services\ZoneResolver;

/**
 * Bilan du promoteur (PR-09) : spectateurs transportés aller et retour, ponctualité, incidents, note moyenne.
 */
class PromoterReport
{
    public function __construct(
        private readonly TripTracker $tracker,
        private readonly ZoneResolver $zones,
    ) {}

    /**
     * @return array{transported: array{outbound: int, return: int}, sold: int, on_time_rate: int|null, departed: int, breakdowns: int, complaints: int, rating: float|null, reviews: int, messages: int, flow: array{labels: list<string>, series: list<array{name: string, values: list<int>, color: string}>, unit: string}}
     */
    public function build(Event $event): array
    {
        $tracking = new ArrivalTracking($event, $this->tracker, $this->zones);
        $quality = (new QualityMetrics($event))->summary();

        return [
            'transported' => $tracking->transported(),
            'sold' => $quality['sold_outbound'],
            'on_time_rate' => $quality['on_time_rate'],
            'departed' => $quality['departed'],
            'breakdowns' => $quality['breakdowns'],
            'complaints' => $quality['complaints_open'] + $quality['complaints_resolved'],
            'rating' => $quality['rating'],
            'reviews' => $quality['reviews'],
            'messages' => $event->promoterMessages()->count(),
            'flow' => $tracking->flow(),
        ];
    }
}
