<?php

namespace App\Models;

use App\Enums\RefundMode;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Remboursement d'un Pass (RG-17 à RG-19, PA-04). Montants en FCFA entiers (RG-16).
 *
 * @property int $id
 * @property string $reference
 * @property int $pass_id
 * @property int $payment_id
 * @property int $customer_id
 * @property int $event_id
 * @property int $company_id
 * @property RefundReason $reason
 * @property int $paid_amount
 * @property int $retained_amount
 * @property int $amount
 * @property RefundStatus $status
 * @property RefundMode $mode
 * @property string|null $provider_reference
 * @property string|null $failure_reason
 * @property string|null $note
 * @property int|null $requested_by
 * @property int|null $processed_by
 * @property Carbon $due_at
 * @property Carbon|null $refunded_at
 * @property Carbon $created_at
 * @property-read Pass $pass
 * @property-read Payment $payment
 * @property-read Customer $customer
 * @property-read Event $event
 * @property-read User|null $processor
 */
#[Fillable([
    'reference', 'pass_id', 'payment_id', 'customer_id', 'event_id', 'company_id', 'reason', 'paid_amount', 'retained_amount', 'amount',
    'status', 'mode', 'provider_reference', 'failure_reason', 'note', 'requested_by', 'processed_by', 'due_at', 'refunded_at',
])]
class Refund extends Model
{
    use Auditable, BelongsToCompany;

    protected function casts(): array
    {
        return [
            'reason' => RefundReason::class,
            'paid_amount' => 'integer',
            'retained_amount' => 'integer',
            'amount' => 'integer',
            'status' => RefundStatus::class,
            'mode' => RefundMode::class,
            'due_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function isOverdue(): bool
    {
        return $this->status !== RefundStatus::Refunded && $this->due_at->isPast();
    }

    /**
     * @return BelongsTo<Pass, $this>
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
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
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
