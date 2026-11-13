<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Point d'embarquement d'un événement (AD-02).
 *
 * @property int $id
 * @property int $event_id
 * @property string $commune
 * @property string $commune_code
 * @property string $place
 * @property Point $location
 * @property string $distance_km
 * @property int $zone
 * @property bool $zone_forced
 * @property string|null $zone_justification
 * @property Carbon $departure_at
 * @property Carbon $return_departure_at
 * @property int|null $travel_minutes
 * @property int $position
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 */
#[Fillable([
    'event_id', 'commune', 'commune_code', 'place', 'location', 'distance_km', 'zone', 'zone_forced',
    'zone_justification', 'departure_at', 'return_departure_at', 'travel_minutes', 'position', 'cancelled_at', 'cancellation_reason',
])]
class BoardingPoint extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'location' => Point::class,
            'distance_km' => 'decimal:2',
            'zone' => 'integer',
            'zone_forced' => 'boolean',
            'departure_at' => 'datetime',
            'return_departure_at' => 'datetime',
            'travel_minutes' => 'integer',
            'position' => 'integer',
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
     * Heure de convocation du spectateur : quelques minutes avant le départ du bus.
     */
    public function boardingCallAt(): Carbon
    {
        return $this->departure_at->copy()->subMinutes((int) config('eventtrans.events.boarding_lead_minutes'));
    }

    /**
     * @return HasMany<Pass, $this>
     */
    public function passes(): HasMany
    {
        return $this->hasMany(Pass::class);
    }
}
