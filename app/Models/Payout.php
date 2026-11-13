<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reversement d'un événement à sa compagnie (RG-14, PA-05) : net = recette brute hors frais − remboursements − commission.
 *
 * @property int $id
 * @property string $reference
 * @property int $event_id
 * @property int $company_id
 * @property int $passes_sold
 * @property int $passes_refunded
 * @property int $gross
 * @property int $refunds
 * @property int $commission
 * @property int $net
 * @property Carbon $due_on
 * @property PayoutStatus $status
 * @property Carbon $calculated_at
 * @property int|null $validated_by
 * @property Carbon|null $validated_at
 * @property int|null $paid_by
 * @property Carbon|null $paid_at
 * @property PayoutMethod|null $payment_method
 * @property string|null $payment_reference
 * @property string|null $proof_path
 * @property-read Event $event
 * @property-read Company $company
 * @property-read User|null $validator
 * @property-read User|null $payer
 */
#[Fillable([
    'reference', 'event_id', 'company_id', 'passes_sold', 'passes_refunded', 'gross', 'refunds', 'commission', 'net', 'due_on', 'status',
    'calculated_at', 'validated_by', 'validated_at', 'paid_by', 'paid_at', 'payment_method', 'payment_reference', 'proof_path',
])]
class Payout extends Model
{
    use Auditable, BelongsToCompany;

    protected function casts(): array
    {
        return [
            'passes_sold' => 'integer',
            'passes_refunded' => 'integer',
            'gross' => 'integer',
            'refunds' => 'integer',
            'commission' => 'integer',
            'net' => 'integer',
            'due_on' => 'date',
            'status' => PayoutStatus::class,
            'calculated_at' => 'datetime',
            'validated_at' => 'datetime',
            'paid_at' => 'datetime',
            'payment_method' => PayoutMethod::class,
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
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
