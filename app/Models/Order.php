<?php

namespace App\Models;

use App\Enums\Offer;
use App\Enums\OrderStatus;
use App\Enums\PassType;
use App\Enums\SalesChannel;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Commande de Pass.
 *
 * @property int $id
 * @property string $reference
 * @property int $customer_id
 * @property int $event_id
 * @property int $boarding_point_id
 * @property Offer $offer
 * @property PassType $pass_type
 * @property int $quantity
 * @property string|null $beneficiary_phone
 * @property int $unit_price
 * @property int $subtotal
 * @property string $fee_rate
 * @property int $fee_amount
 * @property string $commission_rate
 * @property int $commission_amount
 * @property int $company_amount
 * @property int $total
 * @property SalesChannel $channel
 * @property OrderStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $terms_accepted_at
 * @property string|null $terms_version
 * @property-read Customer $customer
 * @property-read Event $event
 * @property-read BoardingPoint $boardingPoint
 */
#[Fillable([
    'reference', 'customer_id', 'event_id', 'boarding_point_id', 'offer', 'pass_type', 'quantity', 'beneficiary_phone',
    'unit_price', 'subtotal', 'fee_rate', 'fee_amount', 'commission_rate', 'commission_amount', 'company_amount',
    'total', 'channel', 'status', 'expires_at', 'paid_at', 'terms_accepted_at', 'terms_version',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'terms_accepted_at' => 'datetime',
            'offer' => Offer::class,
            'pass_type' => PassType::class,
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'subtotal' => 'integer',
            'fee_rate' => 'decimal:2',
            'fee_amount' => 'integer',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'integer',
            'company_amount' => 'integer',
            'total' => 'integer',
            'channel' => SalesChannel::class,
            'status' => OrderStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
     * @return HasMany<Pass, $this>
     */
    public function passes(): HasMany
    {
        return $this->hasMany(Pass::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPayable(): bool
    {
        return $this->status === OrderStatus::PendingPayment && $this->expires_at->isFuture();
    }
}
