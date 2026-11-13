<?php

namespace App\Services\Operations;

use App\Enums\Direction;
use App\Enums\MissionStatus;
use App\Enums\PassStatus;
use App\Models\Complaint;
use App\Models\Mission;
use App\Models\Pass;
use App\Services\Quality\ComplaintService;
use App\Services\Sales\PassNotifier;

/**
 * Retards au départ : au-delà de 20 min (config eventtrans.quality.collective_delay_minutes), une réclamation
 * collective est ouverte pour les passagers du bus et ils sont prévenus (SP-21, RG-20).
 */
class DelayMonitor
{
    public function __construct(
        private readonly ComplaintService $complaints,
        private readonly PassNotifier $notifier,
    ) {}

    public static function delayMinutes(Mission $mission): int
    {
        $departure = $mission->actual_departure_at ?? now();

        return max(0, (int) floor($mission->planned_departure_at->diffInMinutes($departure, false)));
    }

    public function check(Mission $mission): ?Complaint
    {
        $delay = self::delayMinutes($mission);

        if ($delay <= (int) config('eventtrans.quality.collective_delay_minutes') || $mission->replaced_by_mission_id !== null) {
            return null;
        }

        $alreadyOpen = Complaint::query()->withoutGlobalScopes()->where('mission_id', $mission->id)->where('is_collective', true)->exists();
        $complaint = $this->complaints->collectiveDelay($mission, $delay);

        if (! $alreadyOpen) {
            $column = $mission->direction === Direction::Outbound ? 'outbound_mission_id' : 'return_mission_id';
            $passes = Pass::query()->with('holder')->where($column, $mission->id)->whereIn('status', PassStatus::sold())->get();
            $this->notifier->busDelayed($passes->all(), $mission, $delay);
        }

        return $complaint;
    }

    /**
     * Départs non confirmés au-delà du seuil (tâche planifiée toutes les 5 min).
     */
    public function sweep(): int
    {
        $threshold = (int) config('eventtrans.quality.collective_delay_minutes');
        $opened = 0;

        Mission::query()->withoutGlobalScopes()
            ->with(['bus', 'boardingPoint'])
            ->whereIn('status', [MissionStatus::Planned, MissionStatus::Boarding])
            ->whereNull('actual_departure_at')
            ->whereNull('replaced_by_mission_id')
            ->whereBetween('planned_departure_at', [now()->subHours(6), now()->subMinutes($threshold + 1)])
            ->whereDoesntHave('complaints', fn ($query) => $query->withoutGlobalScopes()->where('is_collective', true))
            ->each(function (Mission $mission) use (&$opened) {
                if ($this->check($mission) !== null) {
                    $opened++;
                }
            });

        return $opened;
    }
}
