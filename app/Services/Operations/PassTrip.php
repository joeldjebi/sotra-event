<?php

namespace App\Services\Operations;

use App\Enums\Direction;
use App\Enums\DropZoneUsage;
use App\Enums\MissionStatus;
use App\Enums\PassStatus;
use App\Models\Mission;
use App\Models\Pass;

/**
 * « Mon trajet » d'un Pass (SP-18) : chronologie de l'aller et du retour, bus suivi en direct, arrivée estimée.
 */
class PassTrip
{
    public function __construct(private readonly TripTracker $tracker) {}

    /**
     * @return array{steps: list<array{label: string, at: string|null, done: bool, current: bool}>, live: array<string, mixed>|null}
     */
    public function forPass(Pass $pass): array
    {
        $pass->loadMissing(['boardingPoint', 'event.dropZones', 'event.venue', 'outboundMission.bus']);
        $point = $pass->boardingPoint;
        $event = $pass->event;
        $outbound = $pass->outboundMission;
        $steps = [];

        if ($pass->includesOutbound()) {
            $boarding = in_array($outbound?->status, [MissionStatus::Boarding, MissionStatus::OnRoute, MissionStatus::Arrived, MissionStatus::Finished], true)
                || in_array($pass->status, [PassStatus::OutboundUsed, PassStatus::Completed], true);
            $departed = $outbound?->actual_departure_at !== null;
            $arrived = $outbound?->arrived_at !== null;

            $steps[] = ['label' => "Embarquement à {$point->place}", 'at' => $point->boardingCallAt()->toIso8601String(), 'done' => $boarding];
            $steps[] = ['label' => 'Départ du bus', 'at' => ($outbound->actual_departure_at ?? $point->departure_at)->toIso8601String(), 'done' => $departed];
            $steps[] = [
                'label' => 'Arrivée au '.$event->venue->name,
                'at' => ($outbound->arrived_at ?? $point->departure_at->copy()->addMinutes((int) $point->travel_minutes))->toIso8601String(),
                'done' => $arrived,
            ];
        }

        if ($pass->includesReturn()) {
            $pickup = $event->dropZones->firstWhere('usage', DropZoneUsage::PickUp);
            $returned = in_array($pass->status, [PassStatus::ReturnUsed, PassStatus::Completed], true) && $pass->return_mission_id !== null;

            $steps[] = [
                'label' => 'Votre bus retour vous attend'.($pickup ? ' · '.$pickup->name : ''),
                'at' => $point->return_departure_at->toIso8601String(),
                'done' => $returned,
            ];
            $steps[] = [
                'label' => "Dépose à {$point->commune}",
                'at' => $point->return_departure_at->copy()->addMinutes((int) $point->travel_minutes)->toIso8601String(),
                'done' => $returned && Mission::query()->withoutGlobalScopes()->whereKey($pass->return_mission_id)->whereNotNull('arrived_at')->exists(),
            ];
        }

        $currentIndex = collect($steps)->search(fn (array $step) => ! $step['done']);
        $steps = array_map(fn (array $step, int $index) => $step + ['current' => $index === $currentIndex], $steps, array_keys($steps));

        return ['steps' => $steps, 'live' => $this->live($pass)];
    }

    /**
     * Bus à suivre : celui du retour si le Pass y est monté, sinon celui de l'aller.
     *
     * @return array<string, mixed>|null
     */
    private function live(Pass $pass): ?array
    {
        $mission = $pass->return_mission_id !== null
            ? Mission::query()->withoutGlobalScopes()->with(['bus', 'boardingPoint', 'event'])->find($pass->return_mission_id)
            : $pass->outboundMission;

        if ($mission === null) {
            return null;
        }

        $position = $mission->lastPosition();

        return [
            'mission_id' => $mission->id,
            'direction' => $mission->direction->value,
            'status' => $mission->status->value,
            'status_label' => $mission->status->label(),
            'bus' => $mission->bus?->number,
            'latitude' => $position?->location->getLatitude(),
            'longitude' => $position?->location->getLongitude(),
            'recorded_at' => $position?->recorded_at->toIso8601String(),
            'eta_minutes' => $this->tracker->etaMinutes($mission),
            'destination' => $mission->direction === Direction::Outbound ? $mission->event->venue->name : $mission->boardingPoint->commune,
        ];
    }
}
