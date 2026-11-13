<?php

namespace App\Models;

use App\Enums\Offer;
use App\Enums\PassStatus;
use App\Enums\PassType;
use Database\Factories\PassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Pass transport : un passager, un événement, un point, une offre, un ou deux segments (RG-01).
 *
 * @property int $id
 * @property int $order_id
 * @property int $event_id
 * @property int $boarding_point_id
 * @property int $holder_id
 * @property int|null $outbound_mission_id
 * @property int|null $return_mission_id
 * @property Offer $offer
 * @property PassType $pass_type
 * @property string|null $code
 * @property int $token_version
 * @property PassStatus $status
 * @property int $price
 * @property int $fee_amount
 * @property int $commission_amount
 * @property int $company_amount
 * @property Carbon|null $issued_at
 * @property-read Order $order
 * @property-read Event $event
 * @property-read BoardingPoint $boardingPoint
 * @property-read Customer $holder
 */
#[Fillable([
    'order_id', 'event_id', 'boarding_point_id', 'holder_id', 'outbound_mission_id', 'return_mission_id', 'offer', 'pass_type', 'code', 'token_version',
    'status', 'price', 'fee_amount', 'commission_amount', 'company_amount', 'issued_at',
])]
class Pass extends Model
{
    /** @use HasFactory<PassFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'token_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'offer' => Offer::class,
            'pass_type' => PassType::class,
            'token_version' => 'integer',
            'status' => PassStatus::class,
            'price' => 'integer',
            'fee_amount' => 'integer',
            'commission_amount' => 'integer',
            'company_amount' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
     * @return BelongsTo<Customer, $this>
     */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'holder_id');
    }

    /**
     * @return BelongsTo<Mission, $this>
     */
    public function outboundMission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'outbound_mission_id');
    }

    /**
     * @return BelongsTo<Mission, $this>
     */
    public function returnMission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'return_mission_id');
    }

    /**
     * @return HasMany<PassTransfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(PassTransfer::class);
    }

    /**
     * @return HasOne<Refund, $this>
     */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    /**
     * @return HasMany<Scan, $this>
     */
    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * @return HasMany<Complaint, $this>
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    /**
     * Change le statut en respectant les transitions autorisées.
     */
    public function transitionTo(PassStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new LogicException("Transition de Pass interdite : {$this->status->value} → {$next->value}.");
        }

        $this->update(['status' => $next]);
    }

    public function includesOutbound(): bool
    {
        return $this->pass_type !== PassType::Return;
    }

    public function includesReturn(): bool
    {
        return $this->pass_type !== PassType::Outbound;
    }

    /**
     * « Offrir ce Pass » possible tant que l'aller n'est pas scanné (RG-04).
     */
    public function isTransferable(): bool
    {
        return $this->status === PassStatus::Valid;
    }
}
