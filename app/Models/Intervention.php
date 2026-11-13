<?php

namespace App\Models;

use App\Enums\InterventionResult;
use App\Enums\InterventionStep;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Intervention du service technique (ST-08 à ST-16), numérotée PN-NNNN.
 *
 * @property int $id
 * @property int $breakdown_alert_id
 * @property string $number
 * @property int $tech_team_id
 * @property int|null $technician_id
 * @property InterventionStep $step
 * @property Carbon $taken_at
 * @property Carbon|null $en_route_at
 * @property Carbon|null $on_site_at
 * @property Carbon|null $diagnosis_at
 * @property Carbon|null $closed_at
 * @property InterventionResult|null $result
 * @property bool $towing_requested
 * @property string|null $diagnosis
 * @property string|null $parts
 * @property int|null $duration_min
 * @property string|null $notes
 * @property list<string> $photos
 * @property int|null $replacement_bus_id
 * @property int|null $replacement_mission_id
 * @property-read BreakdownAlert $alert
 * @property-read TechTeam $techTeam
 * @property-read Bus|null $replacementBus
 */
#[Fillable([
    'breakdown_alert_id', 'number', 'tech_team_id', 'technician_id', 'step', 'taken_at', 'en_route_at', 'on_site_at',
    'diagnosis_at', 'closed_at', 'result', 'towing_requested', 'diagnosis', 'parts', 'duration_min', 'notes', 'photos',
    'replacement_bus_id', 'replacement_mission_id',
])]
class Intervention extends Model
{
    use Auditable;

    /**
     * Valeur par défaut aussi en mémoire (la base met '[]'), pour un modèle utilisé juste après sa création.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'photos' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'step' => InterventionStep::class,
            'taken_at' => 'datetime',
            'en_route_at' => 'datetime',
            'on_site_at' => 'datetime',
            'diagnosis_at' => 'datetime',
            'closed_at' => 'datetime',
            'result' => InterventionResult::class,
            'towing_requested' => 'boolean',
            'duration_min' => 'integer',
            'photos' => 'array',
        ];
    }

    /**
     * @return BelongsTo<BreakdownAlert, $this>
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(BreakdownAlert::class, 'breakdown_alert_id');
    }

    /**
     * @return BelongsTo<TechTeam, $this>
     */
    public function techTeam(): BelongsTo
    {
        return $this->belongsTo(TechTeam::class);
    }

    /**
     * @return BelongsTo<Bus, $this>
     */
    public function replacementBus(): BelongsTo
    {
        return $this->belongsTo(Bus::class, 'replacement_bus_id');
    }
}
