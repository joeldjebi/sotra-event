<?php

namespace App\Console\Commands;

use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Models\BoardingPoint;
use App\Models\Pass;
use App\Services\Sales\PassNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rappels avant le départ (SP-21) : J-1 quand la convocation est dans moins de 24 h, H-2 dans moins de 2 h.
 * Planifiée toutes les 15 minutes ; un seul rappel de chaque sorte par Pass (table pass_reminders).
 */
#[Signature('passes:remind')]
#[Description('Envoie les rappels J-1 et H-2 aux détenteurs de Pass')]
class SendPassReminders extends Command
{
    public function handle(PassNotifier $notifier): int
    {
        $lead = (int) config('eventtrans.events.boarding_lead_minutes');
        $sent = 0;

        // Fenêtres sur l'heure de convocation (départ − délai d'embarquement).
        foreach (['h2' => [now(), now()->addHours(2)], 'j1' => [now()->addHours(2), now()->addHours(24)]] as $kind => [$from, $to]) {
            $pointIds = BoardingPoint::query()
                ->whereBetween('departure_at', [$from->copy()->addMinutes($lead), $to->copy()->addMinutes($lead)])
                ->pluck('id');

            Pass::query()
                ->with(['holder', 'event', 'boardingPoint'])
                ->whereIn('boarding_point_id', $pointIds)
                ->where('status', PassStatus::Valid)
                ->whereIn('pass_type', [PassType::RoundTrip, PassType::Outbound])
                ->where('issued_at', '<', now()->subHour())
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('pass_reminders')
                    ->whereColumn('pass_reminders.pass_id', 'passes.id')->where('pass_reminders.kind', $kind))
                ->each(function (Pass $pass) use ($kind, $notifier, &$sent): void {
                    if (DB::table('pass_reminders')->insertOrIgnore(['pass_id' => $pass->id, 'kind' => $kind, 'created_at' => now()]) === 1) {
                        $notifier->reminder($pass, $kind);
                        $sent++;
                    }
                });
        }

        $this->info("{$sent} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
