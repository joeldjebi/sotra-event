<?php

namespace App\Services\Finance;

use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\RefundReason;
use App\Models\Pass;
use App\Services\Sales\PricingService;
use Illuminate\Support\Carbon;

/**
 * Ce que le spectateur peut demander pour son Pass (SP-25, § 11 CDC) :
 * - événement reporté : remboursement intégral sur demande, tant que le Pass n'est pas utilisé (RG-18) ;
 * - sinon, annulation jusqu'à 24 h avant le départ aller, Pass non scanné, 8 % du total retenus (RG-19).
 */
class RefundPolicy
{
    public const string CANCELLATION_FEE_RATE = '8.00';

    public const int CANCELLATION_DEADLINE_HOURS = 24;

    /**
     * @return array{allowed: bool, reason: RefundReason|null, paid: int, retained: int, amount: int, deadline: Carbon|null, message: string}
     */
    public function quote(Pass $pass): array
    {
        $paid = $pass->price + $pass->fee_amount;
        $departure = $pass->pass_type === PassType::Return ? $pass->boardingPoint->return_departure_at : $pass->boardingPoint->departure_at;
        $refuse = fn (string $message): array => ['allowed' => false, 'reason' => null, 'paid' => $paid, 'retained' => 0, 'amount' => 0, 'deadline' => null, 'message' => $message];

        if ($pass->status !== PassStatus::Valid) {
            return $refuse(match ($pass->status) {
                PassStatus::Cancelled, PassStatus::Refunded => 'Ce Pass est déjà annulé.',
                default => 'Un Pass déjà scanné ne peut plus être annulé.',
            });
        }

        if ($pass->event->postponed_at !== null) {
            return $departure->isPast()
                ? $refuse('Le départ est passé.')
                : $this->offer(RefundReason::EventPostponed, $paid, $departure, 'Événement reporté : remboursement intégral, sans frais, sous 48 h.');
        }

        $deadline = $departure->copy()->subHours(self::CANCELLATION_DEADLINE_HOURS);

        if (now()->greaterThan($deadline)) {
            return $refuse('L\'annulation n\'est plus possible à moins de 24 h du départ.');
        }

        return $this->offer(RefundReason::Customer, $paid, $deadline, 'Remboursement sous 72 h sur votre moyen de paiement, 8 % de frais d\'annulation retenus.');
    }

    /**
     * Retenue selon le motif : 8 % du total payé pour une annulation par le spectateur (3 060 → 245), rien sinon.
     */
    public static function retained(RefundReason $reason, int $paid): int
    {
        return $reason->retainsFee() ? PricingService::percent($paid, self::CANCELLATION_FEE_RATE) : 0;
    }

    /**
     * @return array{allowed: bool, reason: RefundReason, paid: int, retained: int, amount: int, deadline: Carbon, message: string}
     */
    private function offer(RefundReason $reason, int $paid, Carbon $deadline, string $message): array
    {
        $retained = self::retained($reason, $paid);

        return ['allowed' => true, 'reason' => $reason, 'paid' => $paid, 'retained' => $retained, 'amount' => $paid - $retained, 'deadline' => $deadline, 'message' => $message];
    }
}
