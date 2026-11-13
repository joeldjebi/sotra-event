<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PassStatus;
use App\Models\Order;
use App\Models\Pass;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Libère les places des commandes non payées dans le délai (statut « Expiré », § 11 CDC).
 * Planifiée chaque minute.
 */
#[Signature('orders:expire')]
#[Description('Expire les commandes non payées et libère leurs places')]
class ExpireOrders extends Command
{
    public function handle(): int
    {
        $count = 0;

        Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('expires_at', '<=', now())
            ->select('id')
            ->chunkById(200, function ($orders) use (&$count): void {
                $ids = $orders->pluck('id');

                DB::transaction(function () use ($ids): void {
                    Order::query()->whereIn('id', $ids)->where('status', OrderStatus::PendingPayment)->update(['status' => OrderStatus::Expired]);
                    Pass::query()->whereIn('order_id', $ids)->where('status', PassStatus::PendingPayment)->update(['status' => PassStatus::Expired]);
                });

                $count += $ids->count();
            });

        $this->info("{$count} commande(s) expirée(s).");

        return self::SUCCESS;
    }
}
