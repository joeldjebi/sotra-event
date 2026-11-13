<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tentative de paiement d'une commande auprès de l'agrégateur.
 *
 * @property int $id
 * @property int $order_id
 * @property string $provider
 * @property string $provider_reference
 * @property PaymentMethod $method
 * @property string|null $msisdn
 * @property int $amount
 * @property PaymentStatus $status
 * @property string|null $failure_reason
 * @property Carbon|null $confirmed_at
 * @property Carbon $created_at
 * @property-read Order $order
 */
#[Fillable(['order_id', 'provider', 'provider_reference', 'method', 'msisdn', 'amount', 'status', 'failure_reason', 'confirmed_at'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
