<?php

namespace App\Models;

use App\Enums\DropZoneUsage;
use App\Models\Concerns\Auditable;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Zone de dépose, de reprise ou d'accueil sur le lieu de l'événement.
 *
 * @property int $id
 * @property int $event_id
 * @property string $name
 * @property DropZoneUsage $usage
 * @property Carbon|null $scheduled_at
 * @property Point $location
 * @property int $radius_m
 */
#[Fillable(['event_id', 'name', 'usage', 'scheduled_at', 'location', 'radius_m'])]
class DropZone extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'usage' => DropZoneUsage::class,
            'scheduled_at' => 'datetime',
            'location' => Point::class,
            'radius_m' => 'integer',
        ];
    }
}
