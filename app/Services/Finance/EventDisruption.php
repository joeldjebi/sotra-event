<?php

namespace App\Services\Finance;

use App\Enums\EventStatus;
use App\Enums\PassStatus;
use App\Enums\RefundReason;
use App\Jobs\NotifyPassHolders;
use App\Models\AuditLog;
use App\Models\BoardingPoint;
use App\Models\Event;
use App\Models\Mission;
use App\Models\Pass;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Annulation ou report d'un événement, annulation d'un départ (AD-13), avec notification de masse :
 * - annulation (événement ou départ) : tous les Pass valides sont remboursés intégralement, frais compris (RG-17) ;
 * - report : les horaires sont décalés, les Pass restent valables, le remboursement sans frais est proposé (RG-18).
 */
class EventDisruption
{
    private const int NOTIFY_CHUNK = 200;

    public function __construct(private readonly RefundService $refunds) {}

    public function cancelEvent(Event $event, string $reason, User $user): int
    {
        $this->ensureActive($event);
        $event->update(['status' => EventStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
        AuditLog::record('event_cancelled', $event, [], ['reason' => $reason]);

        $passes = Pass::query()->where('event_id', $event->id)->where('status', PassStatus::Valid)->pluck('id');
        $this->refundAll($passes->all(), RefundReason::EventCancelled, $user, $reason);
        $this->notify($passes->all(), 'Événement annulé', "{$event->name} est annulé ({$reason}). Votre Pass est remboursé intégralement, frais compris, sur votre moyen de paiement.", 'event_cancelled');

        return $passes->count();
    }

    public function cancelDeparture(BoardingPoint $point, string $reason, User $user): int
    {
        $event = $point->event;
        $this->ensureActive($event);

        if ($point->cancelled_at !== null) {
            throw ValidationException::withMessages(['departure' => 'Ce départ est déjà annulé.']);
        }

        $point->update(['cancelled_at' => now(), 'cancellation_reason' => $reason]);
        AuditLog::record('departure_cancelled', $point, [], ['reason' => $reason]);

        $passes = Pass::query()->where('boarding_point_id', $point->id)->where('status', PassStatus::Valid)->pluck('id');
        $this->refundAll($passes->all(), RefundReason::DepartureCancelled, $user, $reason);
        $this->notify($passes->all(), 'Départ annulé', "le départ de {$point->commune} ({$point->place}) pour {$event->name} est annulé ({$reason}). Votre Pass est remboursé intégralement, frais compris.", 'departure_cancelled');

        return $passes->count();
    }

    /**
     * Décale l'événement et tous ses horaires (points, zones, missions) du même écart.
     */
    public function postpone(Event $event, Carbon $newStart, User $user): int
    {
        $this->ensureActive($event);

        if ($newStart->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['newStart' => 'La nouvelle date doit être dans le futur.']);
        }

        $shift = (int) $event->starts_at->diffInMinutes($newStart, false);

        DB::transaction(function () use ($event, $shift) {
            $event->update([
                'original_starts_at' => $event->original_starts_at ?? $event->starts_at,
                'postponed_at' => now(),
                'starts_at' => $event->starts_at->copy()->addMinutes($shift),
                'ends_at' => $event->ends_at->copy()->addMinutes($shift),
                'doors_open_at' => $event->doors_open_at?->copy()->addMinutes($shift),
            ]);

            foreach ($event->boardingPoints as $point) {
                $point->update([
                    'departure_at' => $point->departure_at->copy()->addMinutes($shift),
                    'return_departure_at' => $point->return_departure_at->copy()->addMinutes($shift),
                ]);
            }

            foreach ($event->dropZones()->whereNotNull('scheduled_at')->get() as $zone) {
                $zone->update(['scheduled_at' => $zone->scheduled_at?->copy()->addMinutes($shift)]);
            }

            Mission::query()->withoutGlobalScopes()->where('event_id', $event->id)
                ->update(['planned_departure_at' => DB::raw("planned_departure_at + interval '{$shift} minutes'")]);
        });

        AuditLog::record('event_postponed', $event, [], ['starts_at' => $event->starts_at->toIso8601String(), 'shift_minutes' => $shift]);

        $passes = Pass::query()->where('event_id', $event->id)->where('status', PassStatus::Valid)->pluck('id');
        $date = ucfirst($event->starts_at->translatedFormat('l j F à H\hi'));
        $this->notify($passes->all(), 'Événement reporté', "{$event->name} est reporté au {$date}. Votre Pass reste valable aux nouveaux horaires. Si vous ne pouvez pas venir, demandez le remboursement intégral dans « Mon Pass ».", 'event_postponed');

        return $passes->count();
    }

    private function ensureActive(Event $event): void
    {
        if (! in_array($event->status, [EventStatus::Accepted, EventStatus::OnSale], true)) {
            throw ValidationException::withMessages(['event' => "Action impossible : l'événement est « {$event->status->label()} »."]);
        }
    }

    /**
     * @param  list<int>  $passIds
     */
    private function refundAll(array $passIds, RefundReason $reason, User $user, string $note): void
    {
        foreach (Pass::query()->with('event')->whereIn('id', $passIds)->lazyById() as $pass) {
            $this->refunds->cancel($pass, $reason, $user, $note);
        }
    }

    /**
     * @param  list<int>  $passIds
     */
    private function notify(array $passIds, string $title, string $body, string $template): void
    {
        foreach (array_chunk($passIds, self::NOTIFY_CHUNK) as $chunk) {
            NotifyPassHolders::dispatch($chunk, $title, $body, $template);
        }
    }
}
