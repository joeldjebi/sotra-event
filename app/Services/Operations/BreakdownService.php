<?php

namespace App\Services\Operations;

use App\Contracts\PushSender;
use App\Enums\AlertStatus;
use App\Enums\BreakdownType;
use App\Enums\BreakdownUrgency;
use App\Enums\InterventionResult;
use App\Enums\InterventionStep;
use App\Enums\MissionStatus;
use App\Enums\StaffRole;
use App\Enums\TechTeamStatus;
use App\Events\MissionUpdated;
use App\Exceptions\ApiException;
use App\Models\BreakdownAlert;
use App\Models\Bus;
use App\Models\EventTechTeam;
use App\Models\Intervention;
use App\Models\Mission;
use App\Models\Staff;
use App\Models\TechTeam;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pannes et interventions (P4 du CDC) : signalement, prise en charge unique, étapes, clôture.
 */
class BreakdownService
{
    public function __construct(
        private readonly PushSender $push,
        private readonly BusReplacement $replacement,
    ) {}

    /**
     * Signalement par le convoyeur (CV-15 à CV-19). Urgente si des passagers sont à bord ou en cas d'accident.
     *
     * @param  list<string>  $photos  chemins déjà stockés
     */
    public function report(Mission $mission, Staff $reporter, BreakdownType $type, ?string $details, ?Point $location, ?int $accuracy, array $photos = [], string $channel = 'app'): BreakdownAlert
    {
        $open = BreakdownAlert::query()->withoutGlobalScopes()->where('mission_id', $mission->id)->where('status', '!=', AlertStatus::Closed)->first();

        if ($open !== null) {
            return $open;
        }

        $onBoard = $mission->onBoardCount();

        $alert = DB::transaction(function () use ($mission, $reporter, $type, $details, $location, $accuracy, $photos, $channel, $onBoard): BreakdownAlert {
            $alert = BreakdownAlert::query()->withoutGlobalScopes()->create([
                'event_id' => $mission->event_id,
                'company_id' => $mission->company_id,
                'mission_id' => $mission->id,
                'bus_id' => $mission->bus_id,
                'reported_by' => $reporter->id,
                'type' => $type,
                'details' => $details,
                'location' => $location ?? $mission->lastPosition()?->location,
                'accuracy_m' => $accuracy,
                'passengers_on_board' => $onBoard,
                'urgency' => $onBoard > 0 || $type === BreakdownType::Accident ? BreakdownUrgency::Urgent : BreakdownUrgency::Normal,
                'status' => AlertStatus::Open,
                'channel' => $channel,
                'mission_status_before' => $mission->status,
                'photos' => $photos,
            ]);

            $mission->update(['status' => MissionStatus::Incident]);

            return $alert;
        });

        $this->notifyTeams($alert);
        MissionUpdated::dispatch($mission->fresh());

        return $alert;
    }

    /**
     * Prise en charge par une équipe (ST-04) : une seule équipe, la première qui accepte.
     */
    public function take(BreakdownAlert $alert, TechTeam $team, ?Staff $technician = null): Intervention
    {
        return DB::transaction(function () use ($alert, $team, $technician): Intervention {
            $alert = BreakdownAlert::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($alert->id);

            if ($alert->status !== AlertStatus::Open) {
                $taker = $alert->techTeam?->code;

                throw new ApiException($taker ? "Déjà pris en charge par l'équipe {$taker}." : 'Cette alerte est clôturée.', 'ALERT_ALREADY_TAKEN', 409);
            }

            if (! EventTechTeam::query()->where('event_id', $alert->event_id)->where('tech_team_id', $team->id)->exists()) {
                throw new ApiException('Votre équipe n\'est pas mobilisée sur cet événement.', 'TEAM_NOT_ASSIGNED', 403);
            }

            $alert->update(['status' => AlertStatus::Taken, 'tech_team_id' => $team->id, 'taken_at' => now()]);
            $team->update(['status' => TechTeamStatus::OnIntervention]);

            return Intervention::query()->create([
                'breakdown_alert_id' => $alert->id,
                'number' => $this->nextNumber(),
                'tech_team_id' => $team->id,
                'technician_id' => $technician?->id,
                'step' => InterventionStep::Taken,
                'taken_at' => now(),
            ]);
        });
    }

    /**
     * Étape suivante de l'intervention (ST-09), horodatée ; on ne revient pas en arrière.
     */
    public function advance(Intervention $intervention, InterventionStep $step): Intervention
    {
        if ($step === InterventionStep::Closed || $step === InterventionStep::Taken || $step->order() <= $intervention->step->order()) {
            throw new ApiException("Étape « {$step->label()} » impossible depuis « {$intervention->step->label()} ».", 'INTERVENTION_STEP_INVALID', 409);
        }

        $intervention->update(['step' => $step, $step->timestampColumn() => now()]);

        return $intervention;
    }

    /**
     * Clôture (ST-12 à ST-16). « Bus remplacé » exige un bus de réserve ; le remorquage peut s'y ajouter.
     *
     * @param  array{result: InterventionResult, diagnosis?: string|null, parts?: string|null, duration_min?: int|null, notes?: string|null, towing_requested?: bool, replacement_bus_id?: int|null, photos?: list<string>}  $data
     */
    public function close(Intervention $intervention, array $data): Intervention
    {
        if ($intervention->step === InterventionStep::Closed) {
            throw new ApiException('Intervention déjà clôturée.', 'INTERVENTION_CLOSED', 409);
        }

        $alert = $intervention->alert()->withoutGlobalScopes()->firstOrFail();
        $mission = Mission::query()->withoutGlobalScopes()->findOrFail($alert->mission_id);
        $replacementMission = null;

        if ($data['result'] === InterventionResult::Replaced) {
            $bus = Bus::query()->withoutGlobalScopes()->find($data['replacement_bus_id'] ?? 0)
                ?? throw ValidationException::withMessages(['replacement_bus_id' => 'Choisissez le bus de réserve.']);

            $replacementMission = $mission->replaced_by_mission_id !== null
                ? Mission::query()->withoutGlobalScopes()->find($mission->replaced_by_mission_id)
                : $this->replacement->replace($mission, $bus);
        }

        DB::transaction(function () use ($intervention, $alert, $mission, $data, $replacementMission): void {
            $intervention->update([
                'step' => InterventionStep::Closed,
                'closed_at' => now(),
                'result' => $data['result'],
                'towing_requested' => (bool) ($data['towing_requested'] ?? $data['result'] === InterventionResult::Towing),
                'diagnosis' => $data['diagnosis'] ?? null,
                'parts' => $data['parts'] ?? null,
                'duration_min' => $data['duration_min'] ?? null,
                'notes' => $data['notes'] ?? null,
                'photos' => array_merge($intervention->photos, $data['photos'] ?? []),
                'replacement_bus_id' => $replacementMission?->bus_id,
                'replacement_mission_id' => $replacementMission?->id,
            ]);

            $alert->update(['status' => AlertStatus::Closed, 'closed_at' => now()]);
            TechTeam::query()->withoutGlobalScopes()->whereKey($intervention->tech_team_id)->update(['status' => TechTeamStatus::Available]);

            if ($data['result'] === InterventionResult::Repaired && $mission->status === MissionStatus::Incident) {
                // Le bus reprend sa mission là où il en était.
                $mission->update(['status' => $alert->mission_status_before === MissionStatus::Incident ? MissionStatus::OnRoute : $alert->mission_status_before]);
            }
        });

        MissionUpdated::dispatch($mission->fresh());

        return $intervention->fresh();
    }

    /**
     * Prévient les techniciens des équipes disponibles mobilisées sur l'événement (ST-06).
     */
    private function notifyTeams(BreakdownAlert $alert): void
    {
        $teamIds = EventTechTeam::query()->where('event_id', $alert->event_id)->pluck('tech_team_id');
        $technicians = Staff::query()->withoutGlobalScopes()
            ->whereIn('tech_team_id', $teamIds)
            ->where('role', StaffRole::Technician)
            ->where('is_active', true)
            ->whereHas('techTeam', fn ($query) => $query->withoutGlobalScopes()->where('status', TechTeamStatus::Available))
            ->get();

        $title = $alert->urgency === BreakdownUrgency::Urgent ? 'Panne urgente · passagers à bord' : 'Nouvelle panne';
        $body = 'Bus '.($alert->bus->number ?? '?')." · {$alert->type->label()} · {$alert->passengers_on_board} passagers";

        foreach ($technicians as $technician) {
            $this->push->sendToStaff($technician, $title, $body, ['alert_id' => $alert->id, 'sound' => $alert->urgency === BreakdownUrgency::Urgent]);
        }
    }

    private function nextNumber(): string
    {
        // Verrou transactionnel : deux prises en charge simultanées ne peuvent obtenir le même numéro.
        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ['interventions.number']);
        $last = (int) Intervention::query()->max(DB::raw('cast(substr(number, 4) as integer)'));

        return sprintf('PN-%04d', $last + 1);
    }
}
