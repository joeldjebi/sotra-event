<?php

namespace App\Actions\Passes;

use App\Enums\PassStatus;
use App\Models\Order;
use App\Services\Operations\MissionPlanner;
use App\Services\Sales\PassCodeGenerator;
use App\Services\Sales\PassNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Émet les Pass d'une commande payée : code ET-AAMM-NNNN-COM, statut « valide », envoi au détenteur (SP-12).
 * Le QR (jeton signé) est calculé à l'affichage à partir de l'id et de la version du Pass.
 */
class IssuePasses
{
    public function __construct(
        private readonly PassCodeGenerator $codes,
        private readonly PassNotifier $notifier,
        private readonly MissionPlanner $planner,
    ) {}

    public function handle(Order $order): void
    {
        $order->loadMissing(['event', 'boardingPoint']);

        $issued = DB::transaction(function () use ($order): int {
            $count = 0;

            foreach ($order->passes()->where('status', PassStatus::PendingPayment)->lockForUpdate()->get() as $pass) {
                $pass->forceFill([
                    'code' => $this->codes->next($order->event, $order->boardingPoint->commune_code),
                    'issued_at' => now(),
                ])->save();
                $pass->transitionTo(PassStatus::Valid);
                $count++;
            }

            return $count;
        });

        if ($issued > 0) {
            // Vente après la génération du plan : le Pass rejoint un bus de son point et de son offre.
            $this->planner->assignPending($order->event);
            $this->notifier->orderPaid($order->fresh(['passes.holder', 'customer', 'event', 'boardingPoint']));
        }
    }
}
