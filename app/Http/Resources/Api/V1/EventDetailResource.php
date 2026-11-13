<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\DropZoneUsage;
use App\Models\BoardingPoint;
use App\Models\DropZone;
use App\Models\Event;
use App\Services\Sales\EventCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Événement et tout ce qu'il faut pour « Choisir mon Pass » (SP-04 à SP-07).
 *
 * @mixin Event
 */
class EventDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $catalog = app(EventCatalog::class);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'doors_open_at' => $this->doors_open_at?->toIso8601String(),
            'venue' => ['id' => $this->venue->id, 'name' => $this->venue->name, 'address' => $this->venue->address],
            'visual_url' => $this->visualUrl(),
            'fee_rate' => (float) $this->fee_rate,
            'max_passes_per_order' => (int) config('eventtrans.sales.max_passes_per_order'),
            // NF-14 : à faire accepter avant l'achat (champ accept_terms de POST /orders).
            'terms' => [
                'version' => (string) config('eventtrans.legal.terms_version'),
                'terms_url' => route('legal.terms'),
                'privacy_url' => route('legal.privacy'),
            ],
            'boarding_points' => $this->boardingPoints->map(fn (BoardingPoint $point) => [
                'id' => $point->id,
                'commune' => $point->commune,
                'commune_code' => $point->commune_code,
                'place' => $point->place,
                'zone' => $point->zone,
                'latitude' => $point->location->getLatitude(),
                'longitude' => $point->location->getLongitude(),
                'boarding_call_at' => $point->boardingCallAt()->toIso8601String(),
                'departure_at' => $point->departure_at->toIso8601String(),
                'return_departure_at' => $point->return_departure_at->toIso8601String(),
            ])->all(),
            'pickup_zones' => $this->dropZones->where('usage', DropZoneUsage::PickUp)->map(fn (DropZone $zone) => [
                'name' => $zone->name,
                'scheduled_at' => $zone->scheduled_at?->toIso8601String(),
            ])->values()->all(),
            'offers' => array_map(fn (array $offer) => [
                'offer' => $offer['offer']->value,
                'label' => $offer['offer']->label(),
                'tagline' => $offer['offer']->tagline(),
                // Liste explicite : un tableau indexé par zone serait réindexé par la sérialisation JSON.
                'prices' => collect($offer['prices'])->flatMap(fn (array $byZone, string $type) => collect($byZone)
                    ->map(fn (int $price, int $zone) => ['zone' => $zone, 'pass_type' => $type, 'price' => $price])->values())->values()->all(),
                'remaining' => $offer['remaining'],
            ], $catalog->offers($this->resource)),
        ];
    }
}
