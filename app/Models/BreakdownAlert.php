<?php

namespace App\Models;

use App\Enums\AlertStatus;
use App\Enums\BreakdownType;
use App\Enums\BreakdownUrgency;
use App\Enums\MissionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Alerte de panne (CV-15 à CV-19).
 *
 * @property int $id
 * @property int $event_id
 * @property int $company_id
 * @property int $mission_id
 * @property int|null $bus_id
 * @property int $reported_by
 * @property BreakdownType $type
 * @property string|null $details
 * @property Point|null $location
 * @property int|null $accuracy_m
 * @property string|null $place_label
 * @property int $passengers_on_board
 * @property BreakdownUrgency $urgency
 * @property AlertStatus $status
 * @property string $channel
 * @property MissionStatus $mission_status_before
 * @property list<string> $photos
 * @property int|null $tech_team_id
 * @property Carbon|null $taken_at
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property-read Mission $mission
 * @property-read Bus|null $bus
 * @property-read Staff $reporter
 * @property-read TechTeam|null $techTeam
 * @property-read Intervention|null $intervention
 */
#[Fillable([
    'event_id', 'company_id', 'mission_id', 'bus_id', 'reported_by', 'type', 'details', 'location', 'accuracy_m',
    'place_label', 'passengers_on_board', 'urgency', 'status', 'channel', 'mission_status_before', 'photos',
    'tech_team_id', 'taken_at', 'closed_at',
])]
class BreakdownAlert extends Model
{
    use Auditable, BelongsToCompany;

    protected function casts(): array
    {
        return [
            'type' => BreakdownType::class,
            'location' => Point::class,
            'accuracy_m' => 'integer',
            'passengers_on_board' => 'integer',
            'urgency' => BreakdownUrgency::class,
            'status' => AlertStatus::class,
            'mission_status_before' => MissionStatus::class,
            'photos' => 'array',
            'taken_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Mission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /**
     * @return BelongsTo<Bus, $this>
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'reported_by');
    }

    /**
     * @return BelongsTo<TechTeam, $this>
     */
    public function techTeam(): BelongsTo
    {
        return $this->belongsTo(TechTeam::class);
    }

    /**
     * @return HasOne<Intervention, $this>
     */
    public function intervention(): HasOne
    {
        return $this->hasOne(Intervention::class);
    }

    /**
     * @return list<string>
     */
    public function photoUrls(): array
    {
        return array_map(fn (string $path) => Storage::disk('public')->url($path), $this->photos);
    }
}
