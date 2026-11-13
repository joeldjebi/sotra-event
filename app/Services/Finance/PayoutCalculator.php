<?php

namespace App\Services\Finance;

use App\Enums\EventStatus;
use App\Enums\PassStatus;
use App\Enums\PayoutStatus;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Pass;
use App\Models\Payout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Relevé de reversement d'un événement (RG-14) :
 * - recette brute hors frais = prix de tous les Pass payés ;
 * - remboursements = prix des Pass annulés ou remboursés ;
 * - commission ACCES EVENT et part compagnie figées sur chaque vente (RG-15) ;
 * - net à reverser = part compagnie des Pass non remboursés (90 % de la recette brute − remboursements).
 * Échéance : 5 jours ouvrés après la fin de l'événement, hors week-ends et jours fériés.
 */
class PayoutCalculator
{
    /**
     * @return array{passes_sold: int, passes_refunded: int, gross: int, refunds: int, commission: int, net: int}
     */
    public function statement(Event $event): array
    {
        $row = Pass::query()->where('event_id', $event->id)
            ->whereIn('status', [...PassStatus::sold(), PassStatus::Cancelled, PassStatus::Refunded])
            ->selectRaw("
                count(*) filter (where status not in ('annule', 'rembourse')) as passes_sold,
                count(*) filter (where status in ('annule', 'rembourse')) as passes_refunded,
                coalesce(sum(price), 0) as gross,
                coalesce(sum(price) filter (where status in ('annule', 'rembourse')), 0) as refunds,
                coalesce(sum(commission_amount) filter (where status not in ('annule', 'rembourse')), 0) as commission,
                coalesce(sum(company_amount) filter (where status not in ('annule', 'rembourse')), 0) as net
            ")
            ->first();

        return [
            'passes_sold' => (int) $row?->getAttribute('passes_sold'),
            'passes_refunded' => (int) $row?->getAttribute('passes_refunded'),
            'gross' => (int) $row?->getAttribute('gross'),
            'refunds' => (int) $row?->getAttribute('refunds'),
            'commission' => (int) $row?->getAttribute('commission'),
            'net' => (int) $row?->getAttribute('net'),
        ];
    }

    public static function dueOn(Carbon $endedAt): Carbon
    {
        /** @var list<string> $holidays */
        $holidays = config('eventtrans.payouts.holidays', []);
        $date = $endedAt->copy()->startOfDay();
        $remaining = (int) config('eventtrans.payouts.delay_business_days');

        while ($remaining > 0) {
            $date->addDay();

            if (! $date->isWeekend() && ! in_array($date->toDateString(), $holidays, true)) {
                $remaining--;
            }
        }

        return $date;
    }

    /**
     * Crée ou recalcule le relevé tant qu'il n'est pas validé.
     */
    public function generate(Event $event): Payout
    {
        $payout = Payout::query()->withoutGlobalScopes()->firstWhere('event_id', $event->id);

        if ($payout !== null && $payout->status !== PayoutStatus::Calculated) {
            return $payout;
        }

        $attributes = $this->statement($event) + [
            'company_id' => $event->company_id,
            'due_on' => self::dueOn($event->ends_at),
            'status' => PayoutStatus::Calculated,
            'calculated_at' => now(),
        ];

        if ($payout === null) {
            return Payout::query()->create($attributes + [
                'event_id' => $event->id,
                'reference' => 'RV-'.$event->ends_at->format('ym').'-'.Str::upper(Str::random(5)),
            ]);
        }

        $payout->update($attributes);

        return $payout;
    }

    /**
     * Tâche quotidienne : clôt les événements terminés et prépare leurs relevés.
     */
    public function run(): int
    {
        Event::query()->withoutGlobalScopes()->where('status', EventStatus::OnSale)->where('ends_at', '<', now())
            ->each(function (Event $event) {
                $event->update(['status' => EventStatus::Finished]);
                AuditLog::record('event_finished', $event);
            });

        $count = 0;

        Event::query()->withoutGlobalScopes()->where('status', EventStatus::Finished)
            ->whereDoesntHave('payout', fn ($query) => $query->withoutGlobalScopes()->where('status', '!=', PayoutStatus::Calculated))
            ->each(function (Event $event) use (&$count) {
                $this->generate($event);
                $count++;
            });

        return $count;
    }
}
