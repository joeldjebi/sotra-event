<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Transfert d'un Pass à un autre numéro (RG-04).
 *
 * @property int $id
 * @property int $pass_id
 * @property int $from_customer_id
 * @property int $to_customer_id
 * @property int $previous_token_version
 * @property Carbon $created_at
 * @property-read Customer $from
 * @property-read Customer $to
 */
#[Fillable(['pass_id', 'from_customer_id', 'to_customer_id', 'previous_token_version'])]
class PassTransfer extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function from(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'from_customer_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function to(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'to_customer_id');
    }
}
