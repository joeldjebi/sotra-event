<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Équipe technique mobilisée sur un événement, avec son secteur.
 *
 * @property int $id
 * @property int $event_id
 * @property int $tech_team_id
 * @property string $sector
 * @property-read TechTeam $techTeam
 * @property-read Event $event
 */
#[Fillable(['event_id', 'tech_team_id', 'sector'])]
class EventTechTeam extends Model
{
    use Auditable;

    /**
     * @return BelongsTo<TechTeam, $this>
     */
    public function techTeam(): BelongsTo
    {
        return $this->belongsTo(TechTeam::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withoutGlobalScopes();
    }
}
