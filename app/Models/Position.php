<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Position GPS d'une mission.
 *
 * @property int $id
 * @property int $mission_id
 * @property Carbon $recorded_at
 * @property Point $location
 * @property int|null $accuracy_m
 * @property string|null $speed_kmh
 */
#[Fillable(['mission_id', 'recorded_at', 'location', 'accuracy_m', 'speed_kmh'])]
class Position extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'location' => Point::class,
            'accuracy_m' => 'integer',
            'speed_kmh' => 'decimal:1',
        ];
    }
}
