<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Webhook reçu de l'agrégateur.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $provider_reference
 * @property array<string, mixed> $payload
 * @property Carbon|null $processed_at
 */
#[Fillable(['provider', 'event_id', 'provider_reference', 'payload', 'processed_at'])]
class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
