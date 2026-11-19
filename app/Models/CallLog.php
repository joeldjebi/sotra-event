<?php

namespace App\Models;

use App\Enums\CallReason;
use Database\Factories\CallLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Appel traité par le centre d'appel.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property string $phone
 * @property int|null $user_id
 * @property int|null $pass_id
 * @property CallReason $reason
 * @property string|null $notes
 * @property Carbon $created_at
 * @property-read User|null $agent
 * @property-read Pass|null $pass
 * @property-read Customer|null $customer
 */
#[Fillable(['customer_id', 'phone', 'user_id', 'pass_id', 'reason', 'notes'])]
class CallLog extends Model
{
    /** @use HasFactory<CallLogFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reason' => CallReason::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Pass, $this>
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
