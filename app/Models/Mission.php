<?php

namespace App\Models;

use App\Enums\Direction;
use App\Enums\MissionStatus;
use App\Enums\Offer;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Mission : un bus pour un point, une offre et un sens (§ 13 CDC).
 *
 * @property int $id
 * @property int $event_id
 * @property int $company_id
 * @property int $boarding_point_id
 * @property Offer $offer
 * @property Direction $direction
 * @property int $sequence
 * @property int $seats
 * @property int|null $bus_id
 * @property int|null $driver_id
 * @property int|null $conveyor_id
 * @property int|null $device_id
 * @property Carbon $planned_departure_at
 * @property Carbon|null $actual_departure_at
 * @property Carbon|null $arrived_at
 * @property MissionStatus $status
 * @property int|null $replaced_by_mission_id
 * @property-read Event $event
 * @property-read BoardingPoint $boardingPoint
 * @property-read Bus|null $bus
 * @property-read Staff|null $driver
 * @property-read Staff|null $conveyor
 * @property-read Device|null $device
 */
#[Fillable([
    'event_id', 'company_id', 'boarding_point_id', 'offer', 'direction', 'sequence', 'seats', 'bus_id', 'driver_id',
    'conveyor_id', 'device_id', 'planned_departure_at', 'actual_departure_at', 'arrived_at', 'status', 'replaced_by_mission_id',
])]
class Mission extends Model
{
    use Auditable, BelongsToCompany;

    protected function casts(): array
    {
        return [
            'offer' => Offer::class,
            'direction' => Direction::class,
            'sequence' => 'integer',
            'seats' => 'integer',
            'planned_departure_at' => 'datetime',
            'actual_departure_at' => 'datetime',
            'arrived_at' => 'datetime',
            'status' => MissionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<BoardingPoint, $this>
     */
    public function boardingPoint(): BelongsTo
    {
        return $this->belongsTo(BoardingPoint::class);
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
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'driver_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function conveyor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'conveyor_id');
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Pass attendus à l'aller (manifeste, CV-12).
     *
     * @return HasMany<Pass, $this>
     */
    public function outboundPasses(): HasMany
    {
        return $this->hasMany(Pass::class, 'outbound_mission_id');
    }

    /**
     * Mission de remplacement après une panne (RG-20).
     *
     * @return BelongsTo<Mission, $this>
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'replaced_by_mission_id');
    }

    /**
     * @return HasMany<Scan, $this>
     */
    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    /**
     * @return HasMany<Position, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * @return HasMany<Complaint, $this>
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Dernière position connue.
     */
    public function lastPosition(): ?Position
    {
        return $this->positions()->latest('recorded_at')->first();
    }

    /**
     * Passagers à bord : scans acceptés (vert ou orange) de cette mission (CV-03).
     */
    public function onBoardCount(): int
    {
        return $this->scans()->whereIn('result', ['vert', 'orange'])->where('conflict', false)->count();
    }

    /**
     * Places du bus affecté, sinon places prévues par la compagnie.
     */
    public function capacity(): int
    {
        return $this->bus->capacity ?? $this->seats;
    }

    public function label(): string
    {
        return "{$this->direction->label()} · {$this->boardingPoint->commune} · {$this->offer->label()} n° {$this->sequence}";
    }

    public function isFullyStaffed(): bool
    {
        return $this->bus_id !== null && $this->driver_id !== null && $this->conveyor_id !== null;
    }
}
