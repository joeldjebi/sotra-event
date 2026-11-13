<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Position GPS d'une mission, collectée uniquement pendant la mission (NF-12).
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
    use MassPrunable;

    public const UPDATED_AT = null;

    /**
     * Positions des événements terminés depuis plus de 90 jours (NF-12), purgées chaque nuit.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        $before = now()->subDays((int) config('eventtrans.privacy.positions_retention_days'));

        return self::query()->whereIn('mission_id', Mission::query()->withoutGlobalScopes()->select('missions.id')
            ->join('events', 'events.id', '=', 'missions.event_id')->where('events.ends_at', '<', $before));
    }

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
