<?php

namespace App\Actions\Payments;

use App\Actions\Passes\IssuePasses;
use App\Enums\OrderStatus;
use App\Enums\PassStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Services\Sales\CapacityService;
use Illuminate\Support\Facades\DB;

/**
 * Applique le résultat d'un paiement, de façon idempotente (PA-02) : un paiement déjà traité est ignoré,
 * et une commande n'émet ses Pass qu'une seule fois, même si l'agrégateur notifie plusieurs fois.
 */
class ApplyPaymentResult
{
    public function __construct(
        private readonly IssuePasses $issuePasses,
        private readonly CapacityService $capacity,
    ) {}

    public function handle(Payment $payment, PaymentStatus $status, ?string $reason = null): void
    {
        $issuedOrder = DB::transaction(function () use ($payment, $status, $reason) {
            $payment = Payment::query()->with('order.event')->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Pending || $status === PaymentStatus::Pending) {
                return null;
            }

            if ($status === PaymentStatus::Failed) {
                $payment->update(['status' => PaymentStatus::Failed, 'failure_reason' => $reason]);

                return null;
            }

            $payment->update(['status' => PaymentStatus::Succeeded, 'confirmed_at' => now()]);
            $order = $payment->order()->lockForUpdate()->firstOrFail();

            if ($order->status === OrderStatus::Paid) {
                // Second paiement réussi pour une commande déjà payée : à rembourser, aucun Pass en plus.
                AuditLog::record('duplicate_payment', $payment, [], ['order' => $order->reference, 'amount' => $payment->amount]);

                return null;
            }

            if ($order->status !== OrderStatus::PendingPayment || $order->expires_at->isPast()) {
                $this->capacity->lockOffer($order->event, $order->offer);
                $remaining = $this->capacity->remaining($order->event, $order->offer, $order->pass_type);

                if ($remaining === null || $remaining < $order->quantity) {
                    $order->update(['status' => OrderStatus::RefundRequired]);
                    $order->passes()->where('status', PassStatus::PendingPayment)->update(['status' => PassStatus::Expired]);
                    AuditLog::record('late_payment_refund_required', $order, [], ['payment' => $payment->provider_reference]);

                    return null;
                }

                // Paiement tardif mais places encore disponibles : les Pass expirés redeviennent en attente.
                $order->passes()->where('status', PassStatus::Expired)->update(['status' => PassStatus::PendingPayment]);
            }

            $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

            return $order;
        });

        if ($issuedOrder !== null) {
            $this->issuePasses->handle($issuedOrder);
        }
    }
}
