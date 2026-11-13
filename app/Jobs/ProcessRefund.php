<?php

namespace App\Jobs;

use App\Models\Refund;
use App\Services\Finance\RefundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envoi d'un remboursement à l'agrégateur, hors de la requête du spectateur (annulations en masse comprises).
 */
class ProcessRefund implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $refundId) {}

    public function handle(RefundService $refunds): void
    {
        $refund = Refund::query()->withoutGlobalScopes()->find($this->refundId);

        if ($refund !== null) {
            $refunds->process($refund);
        }
    }
}
