<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Événement dans le catalogue (SP-02, SP-03).
 *
 * @mixin Event
 */
class EventSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'starts_at' => $this->starts_at->toIso8601String(),
            'venue' => ['id' => $this->venue->id, 'name' => $this->venue->name, 'city' => $this->venue->city],
            'featured' => $this->featured,
            // Agrégats calculés par EventCatalog::listing() (withCount / withMin).
            'communes_count' => (int) $this->resource->getAttribute('communes_count'),
            'from_price' => $this->resource->getAttribute('from_price') !== null ? (int) $this->resource->getAttribute('from_price') : null,
            'visual_url' => $this->visualUrl(),
        ];
    }
}
