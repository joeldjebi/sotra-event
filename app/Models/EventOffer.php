<?php

namespace App\Models;

use App\Enums\Offer;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Offre active d'un événement et capacité fixée par la compagnie (RG-08).
 *
 * @property int $id
 * @property int $event_id
 * @property Offer $offer
 * @property int|null $bus_count
 * @property int|null $seats_per_bus
 */
#[Fillable(['event_id', 'offer', 'bus_count', 'seats_per_bus'])]
class EventOffer extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'offer' => Offer::class,
            'bus_count' => 'integer',
            'seats_per_bus' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Capacité de l'offre = bus × places (RG-08), ou null si non définie.
     */
    public function capacity(): ?int
    {
        return $this->bus_count && $this->seats_per_bus ? $this->bus_count * $this->seats_per_bus : null;
    }
}
