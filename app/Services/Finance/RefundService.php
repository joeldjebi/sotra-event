<?php

namespace App\Services\Finance;

use App\Contracts\PaymentGateway;
use App\Data\RefundResult;
use App\Enums\PassStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundMode;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Exceptions\ApiException;
use App\Jobs\ProcessRefund;
use App\Models\AuditLog;
use App\Models\Pass;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Sales\PassNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Annulation d'un Pass et remboursement (RG-17 à RG-19, PA-04) :
 * le Pass passe « annulé » (son QR est refusé, la place est libérée), un remboursement est créé puis
 * envoyé à l'agrégateur ; s'il ne peut pas rembourser par API, il rejoint la liste de traitement manuel.
 */
class RefundService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PassNotifier $notifier,
    ) {}

    public function cancel(Pass $pass, RefundReason $reason, ?User $requestedBy = null, ?string $note = null): Refund
    {
        $refund = DB::transaction(function () use ($pass, $reason, $requestedBy, $note): Refund {
            $pass = Pass::query()->lockForUpdate()->findOrFail($pass->id);

            if ($pass->status !== PassStatus::Valid) {
                throw new ApiException('Seul un Pass valide et non scanné peut être annulé.', 'PASS_NOT_CANCELLABLE', 409);
            }

            $payment = Payment::query()->where('order_id', $pass->order_id)->where('status', PaymentStatus::Succeeded)->latest('id')->first()
                ?? throw new ApiException('Paiement d\'origine introuvable.', 'PAYMENT_NOT_FOUND', 409);

            $paid = $pass->price + $pass->fee_amount;
            $retained = RefundPolicy::retained($reason, $paid);
            $pass->transitionTo(PassStatus::Cancelled);

            $refund = Refund::query()->create([
                'reference' => 'RB-'.Str::upper(Str::random(8)),
                'pass_id' => $pass->id,
                'payment_id' => $payment->id,
                'customer_id' => $pass->holder_id,
                'event_id' => $pass->event_id,
                'company_id' => $pass->event->company_id,
                'reason' => $reason,
                'paid_amount' => $paid,
                'retained_amount' => $retained,
                'amount' => $paid - $retained,
                'status' => RefundStatus::Pending,
                'mode' => RefundMode::Api,
                'note' => $note,
                'requested_by' => $requestedBy?->id,
                'due_at' => now()->addHours($reason->delayHours()),
            ]);

            AuditLog::record('pass_cancelled', $pass, ['status' => PassStatus::Valid->value], ['status' => PassStatus::Cancelled->value, 'refund' => $refund->reference, 'amount' => $refund->amount]);

            return $refund;
        });

        ProcessRefund::dispatch($refund->id)->afterCommit();

        return $refund;
    }

    /**
     * Envoie le remboursement à l'agrégateur (tâche en file, relançable depuis la console).
     */
    public function process(Refund $refund): Refund
    {
        if ($refund->status === RefundStatus::Refunded || $refund->mode === RefundMode::Manual) {
            return $refund;
        }

        $result = $this->gateway->refund($refund->payment, $refund->amount, $refund->reference);

        match ($result->status) {
            RefundResult::REFUNDED => $this->complete($refund, $result->reference, RefundMode::Api),
            RefundResult::UNSUPPORTED => $refund->update(['mode' => RefundMode::Manual, 'failure_reason' => $result->message]),
            default => $refund->update(['status' => RefundStatus::Failed, 'failure_reason' => $result->message]),
        };

        return $refund;
    }

    /**
     * Relance un remboursement en échec par l'API.
     */
    public function retry(Refund $refund): Refund
    {
        $refund->update(['status' => RefundStatus::Pending, 'mode' => RefundMode::Api, 'failure_reason' => null]);

        return $this->process($refund);
    }

    /**
     * Traitement manuel tracé (PA-04) : le support a remboursé hors API et saisit la référence de l'opération.
     */
    public function markRefunded(Refund $refund, User $user, string $reference): Refund
    {
        $refund->update(['processed_by' => $user->id]);

        return $this->complete($refund, $reference, RefundMode::Manual);
    }

    private function complete(Refund $refund, ?string $reference, RefundMode $mode): Refund
    {
        DB::transaction(function () use ($refund, $reference, $mode) {
            $refund->update(['status' => RefundStatus::Refunded, 'mode' => $mode, 'provider_reference' => $reference, 'failure_reason' => null, 'refunded_at' => now()]);
            $refund->pass->transitionTo(PassStatus::Refunded);
        });

        $this->notifier->refunded($refund->load(['pass', 'customer', 'payment']));

        return $refund;
    }
}
