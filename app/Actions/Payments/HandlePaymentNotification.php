<?php

namespace App\Actions\Payments;

use App\Data\WebhookNotification;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Traite un webhook de l'agrégateur (PA-02) : enregistré une seule fois (unicité provider + id),
 * puis appliqué au paiement correspondant.
 */
class HandlePaymentNotification
{
    public function __construct(private readonly ApplyPaymentResult $apply) {}

    public function handle(string $provider, WebhookNotification $notification): void
    {
        $event = DB::transaction(function () use ($provider, $notification): ?PaymentEvent {
            $event = PaymentEvent::query()->lockForUpdate()->firstOrCreate(
                ['provider' => $provider, 'event_id' => $notification->eventId],
                ['provider_reference' => $notification->reference, 'payload' => $notification->payload],
            );

            return $event->processed_at === null ? $event : null;
        });

        if ($event === null) {
            return;
        }

        $payment = Payment::query()->where('provider', $provider)->where('provider_reference', $notification->reference)->first();

        if ($payment === null) {
            Log::warning('Webhook de paiement pour une référence inconnue', ['provider' => $provider, 'reference' => $notification->reference]);
        } else {
            $this->apply->handle($payment, $notification->status, $notification->failureReason);
        }

        $event->update(['processed_at' => now()]);
    }
}
