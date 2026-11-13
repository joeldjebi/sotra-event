<?php

namespace App\Console\Commands;

use App\Actions\Payments\ApplyPaymentResult;
use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Interroge l'agrégateur pour tout paiement resté en attente (PA-03). Planifiée toutes les 5 minutes.
 */
#[Signature('payments:reconcile')]
#[Description("Vérifie auprès de l'agrégateur les paiements restés en attente")]
class ReconcilePayments extends Command
{
    public function handle(PaymentGateway $gateway, ApplyPaymentResult $apply): int
    {
        $resolved = 0;

        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('provider', $gateway->name())
            ->where('created_at', '<=', now()->subMinutes((int) config('eventtrans.payment.reconcile_after_minutes')))
            ->where('created_at', '>=', now()->subDays(3))
            ->each(function (Payment $payment) use ($gateway, $apply, &$resolved): void {
                $status = $gateway->fetchStatus($payment);

                if ($status !== PaymentStatus::Pending) {
                    $apply->handle($payment, $status);
                    $resolved++;
                }
            });

        $this->info("{$resolved} paiement(s) réconcilié(s).");

        return self::SUCCESS;
    }
}
