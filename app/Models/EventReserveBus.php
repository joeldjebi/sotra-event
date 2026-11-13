<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bus de réserve d'un événement et sa position d'attente (ex. « dépôt Yopougon », « parking P5 Ebimpé »).
 *
 * @property int $id
 * @property int $event_id
 * @property int $bus_id
 * @property string $waiting_place
 * @property-read Bus $bus
 */
#[Fillable(['event_id', 'bus_id', 'waiting_place'])]
class EventReserveBus extends Model
{
    use Auditable;

    /**
     * @return BelongsTo<Bus, $this>
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }
}
