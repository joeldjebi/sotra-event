<?php

namespace App\Data;

use App\Enums\PaymentStatus;

/**
 * Notification de paiement reçue de l'agrégateur, signature déjà vérifiée.
 *
 * @phpstan-type Payload array<string, mixed>
 */
final readonly class WebhookNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $reference,
        public PaymentStatus $status,
        public array $payload,
        public ?string $failureReason = null,
    ) {}
}
