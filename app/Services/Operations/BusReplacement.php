<?php

namespace App\Services\Operations;

use App\Enums\BusStatus;
use App\Enums\Direction;
use App\Enums\MissionStatus;
use App\Enums\ScanResult;
use App\Events\MissionUpdated;
use App\Models\AuditLog;
use App\Models\Bus;
use App\Models\EventReserveBus;
use App\Models\Mission;
use App\Models\Pass;
use App\Services\Sales\PassNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Remplacement d'un bus en panne par un bus de réserve (RG-20, ST-15) : une mission de remplacement est créée
 * avec le même convoyeur, les Pass passent sur le nouveau bus sans action du passager, le convoyeur refait un
 * scan de contrôle à la montée, et les passagers concernés sont prévenus.
 */
class BusReplacement
{
    public function __construct(private readonly PassNotifier $notifier) {}

    public function replace(Mission $mission, Bus $reserveBus): Mission
    {
        if ($reserveBus->company_id !== $mission->company_id || in_array($reserveBus->status, [BusStatus::Broken, BusStatus::OutOfService], true)) {
            throw ValidationException::withMessages(['replacement_bus_id' => 'Choisissez un bus utilisable de la compagnie.']);
        }

        if (Mission::query()->withoutGlobalScopes()->where('event_id', $mission->event_id)->where('direction', $mission->direction)
            ->where('bus_id', $reserveBus->id)->whereKeyNot($mission->id)->exists()) {
            throw ValidationException::withMessages(['replacement_bus_id' => "Le bus {$reserveBus->number} est déjà affecté à une mission."]);
        }

        [$replacement, $passes] = DB::transaction(function () use ($mission, $reserveBus): array {
            $replacement = Mission::query()->withoutGlobalScopes()->create([
                'event_id' => $mission->event_id,
                'company_id' => $mission->company_id,
                'boarding_point_id' => $mission->boarding_point_id,
                'offer' => $mission->offer,
                'direction' => $mission->direction,
                'sequence' => (int) Mission::query()->withoutGlobalScopes()->where('event_id', $mission->event_id)
                    ->where('boarding_point_id', $mission->boarding_point_id)->where('offer', $mission->offer)
                    ->where('direction', $mission->direction)->max('sequence') + 1,
                'seats' => max($mission->seats, $reserveBus->capacity),
                'bus_id' => $reserveBus->id,
                'conveyor_id' => $mission->conveyor_id,
                'device_id' => $mission->device_id,
                'planned_departure_at' => $mission->planned_departure_at,
                'actual_departure_at' => $mission->actual_departure_at,
                'status' => MissionStatus::Boarding,
            ]);

            // Passagers à transférer : ceux déjà montés, et à l'aller ceux qui étaient attendus dans ce bus.
            $boardedIds = $mission->scans()->whereIn('result', [ScanResult::Green, ScanResult::Orange])->where('conflict', false)->pluck('pass_id')->filter();
            $column = $mission->direction === Direction::Outbound ? 'outbound_mission_id' : 'return_mission_id';
            $passes = Pass::query()->where($column, $mission->id)->orWhereIn('id', $boardedIds)->get();
            Pass::query()->whereIn('id', $passes->pluck('id'))->update([$column => $replacement->id]);

            $mission->update(['replaced_by_mission_id' => $replacement->id, 'status' => MissionStatus::Incident]);

            if ($mission->bus_id !== null) {
                Bus::query()->withoutGlobalScopes()->whereKey($mission->bus_id)->update(['status' => BusStatus::Broken]);
            }

            EventReserveBus::query()->where('event_id', $mission->event_id)->where('bus_id', $reserveBus->id)->delete();
            AuditLog::record('bus_replaced', $mission, ['bus_id' => $mission->bus_id], ['bus_id' => $reserveBus->id, 'mission_id' => $replacement->id]);

            return [$replacement, $passes];
        });

        $this->notifier->busReplaced($passes->load(['holder', 'event', 'boardingPoint'])->all(), $reserveBus);
        MissionUpdated::dispatch($mission->fresh());
        MissionUpdated::dispatch($replacement);

        return $replacement;
    }
}
