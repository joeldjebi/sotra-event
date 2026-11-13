<?php

namespace App\Http\Controllers\Api\V1\Tech;

use App\Enums\AlertStatus;
use App\Enums\BreakdownUrgency;
use App\Enums\InterventionResult;
use App\Enums\InterventionStep;
use App\Enums\TechTeamStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\BreakdownAlert;
use App\Models\EventReserveBus;
use App\Models\EventTechTeam;
use App\Models\Intervention;
use App\Models\Staff;
use App\Models\TechTeam;
use App\Services\Operations\BreakdownService;
use App\Services\ZoneResolver;
use Clickbar\Magellan\Data\Geometries\Point;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * App service technique (ST-01 à ST-16) : équipe, alertes, prise en charge, intervention, clôture.
 */
#[Group('Service technique', 'App service technique · équipe, alertes de panne, prise en charge, étapes, clôture et remplacement de bus.', weight: 7)]
class TechController extends Controller
{
    private const SPEED_KMH = 30;

    public function __construct(
        private readonly BreakdownService $breakdowns,
        private readonly ZoneResolver $geo,
    ) {}

    /**
     * Mon équipe : statut, membres, événements et secteurs couverts (ST-01).
     */
    public function team(Request $request): JsonResponse
    {
        $team = $this->teamOf($request)->load(['members', 'depot']);

        return response()->json(['data' => [
            'id' => $team->id,
            'code' => $team->code,
            'type' => $team->type->value,
            'type_label' => $team->type->label(),
            'status' => $team->status->value,
            'members' => $team->members->map(fn (Staff $member) => ['name' => $member->fullName(), 'phone' => $member->phone])->values(),
            'assignments' => EventTechTeam::query()->with('event')->where('tech_team_id', $team->id)->get()
                ->filter(fn (EventTechTeam $assignment) => $assignment->event->ends_at->isFuture())
                ->map(fn (EventTechTeam $assignment) => ['event_id' => $assignment->event_id, 'event' => $assignment->event->name, 'sector' => $assignment->sector])
                ->values(),
        ]]);
    }

    /**
     * Changer la disponibilité de l'équipe (Disponible / Indisponible).
     */
    public function updateTeam(Request $request): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in([TechTeamStatus::Available->value, TechTeamStatus::Unavailable->value])]]);
        $team = $this->teamOf($request);
        $team->update(['status' => $validated['status']]);

        return response()->json(['data' => ['status' => $team->status->value]]);
    }

    /**
     * Position de l'équipe (calcul des distances jusqu'aux pannes).
     */
    public function position(Request $request): JsonResponse
    {
        $validated = $request->validate(['latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180']]);
        $this->teamOf($request)->update(['location' => Point::makeGeodetic((float) $validated['latitude'], (float) $validated['longitude']), 'located_at' => now()]);

        return response()->json(['data' => ['recorded' => true]]);
    }

    /**
     * Alertes ouvertes et en cours des événements de l'équipe, la plus urgente en haut (ST-02, ST-03).
     */
    public function alerts(Request $request): JsonResponse
    {
        $team = $this->teamOf($request);
        $eventIds = EventTechTeam::query()->where('tech_team_id', $team->id)->pluck('event_id');

        $alerts = BreakdownAlert::query()->withoutGlobalScopes()
            ->with(['bus', 'mission.boardingPoint', 'techTeam', 'reporter', 'intervention'])
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', [AlertStatus::Open, AlertStatus::Taken])
            ->orderByRaw("case urgency when 'urgent' then 0 else 1 end")
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $alerts->map(fn (BreakdownAlert $alert) => $this->presentAlert($alert, $team))->values()]);
    }

    /**
     * Prendre en charge une alerte (ST-04) : refusé (409) si une autre équipe l'a déjà prise.
     */
    public function take(Request $request, int $alert): JsonResponse
    {
        $team = $this->teamOf($request);
        $model = BreakdownAlert::query()->withoutGlobalScopes()->findOrFail($alert);
        $intervention = $this->breakdowns->take($model, $team, $this->technician($request));

        return response()->json(['data' => $this->presentIntervention($intervention->fresh(['alert.bus', 'alert.mission.boardingPoint', 'alert.reporter']))], 201);
    }

    /**
     * Bus de réserve de l'événement de l'alerte, du plus proche au plus éloigné (ST-05).
     */
    public function reserveBuses(Request $request): JsonResponse
    {
        $this->teamOf($request);
        $alert = BreakdownAlert::query()->withoutGlobalScopes()->findOrFail($request->integer('alert'));

        $reserves = EventReserveBus::query()->with('bus.depot')->where('event_id', $alert->event_id)->get()
            ->map(function (EventReserveBus $reserve) use ($alert) {
                $from = $reserve->bus->depot?->location;
                $distance = $from !== null && $alert->location !== null ? $this->geo->distanceKm($from, $alert->location) : null;

                return ['bus_id' => $reserve->bus_id, 'number' => $reserve->bus->number, 'capacity' => $reserve->bus->capacity, 'waiting_place' => $reserve->waiting_place, 'distance_km' => $distance];
            })
            ->sortBy(fn (array $reserve) => $reserve['distance_km'] ?? PHP_FLOAT_MAX)
            ->values();

        return response()->json(['data' => $reserves]);
    }

    /**
     * Historique des interventions de l'équipe.
     */
    public function history(Request $request): JsonResponse
    {
        $team = $this->teamOf($request);
        $interventions = Intervention::query()->with(['alert' => fn ($query) => $query->withoutGlobalScopes()->with(['bus', 'mission.boardingPoint', 'reporter'])])
            ->where('tech_team_id', $team->id)->latest('taken_at')->limit(50)->get();

        return response()->json(['data' => $interventions->map(fn (Intervention $intervention) => $this->presentIntervention($intervention))->values()]);
    }

    /**
     * Détail d'une intervention (ST-08 à ST-11).
     */
    public function show(Request $request, Intervention $intervention): JsonResponse
    {
        $this->ensureTeam($request, $intervention);

        return response()->json(['data' => $this->presentIntervention($intervention->load(['alert' => fn ($query) => $query->withoutGlobalScopes()->with(['bus', 'mission.boardingPoint', 'reporter'])]))]);
    }

    /**
     * Passer à l'étape suivante : en_route, sur_place, diagnostic (ST-09).
     */
    public function step(Request $request, Intervention $intervention): JsonResponse
    {
        $this->ensureTeam($request, $intervention);
        $validated = $request->validate(['step' => ['required', Rule::in([InterventionStep::EnRoute->value, InterventionStep::OnSite->value, InterventionStep::Diagnosis->value])]]);

        $this->breakdowns->advance($intervention, InterventionStep::from($validated['step']));

        return $this->show($request, $intervention->fresh());
    }

    /**
     * Clôturer l'intervention (ST-12 à ST-16). « remplace » exige replacement_bus_id.
     */
    public function close(Request $request, Intervention $intervention): JsonResponse
    {
        $this->ensureTeam($request, $intervention);

        $validated = $request->validate([
            'result' => ['required', Rule::enum(InterventionResult::class)],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'parts' => ['nullable', 'string', 'max:255'],
            'duration_min' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'towing_requested' => ['boolean'],
            'replacement_bus_id' => [Rule::requiredIf($request->input('result') === InterventionResult::Replaced->value), 'nullable', 'integer'],
            'photos' => ['array', 'max:6'],
            'photos.*' => ['image', 'max:6144'],
        ]);

        $photos = array_map(fn (UploadedFile $photo) => $photo->store('interventions', 'public'), $request->file('photos', []));

        $this->breakdowns->close($intervention, [
            'result' => InterventionResult::from($validated['result']),
            'diagnosis' => $validated['diagnosis'] ?? null,
            'parts' => $validated['parts'] ?? null,
            'duration_min' => $validated['duration_min'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'towing_requested' => (bool) ($validated['towing_requested'] ?? false),
            'replacement_bus_id' => $validated['replacement_bus_id'] ?? null,
            'photos' => array_values(array_filter($photos)),
        ]);

        return $this->show($request, $intervention->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAlert(BreakdownAlert $alert, TechTeam $team): array
    {
        $distance = $team->location !== null && $alert->location !== null ? $this->geo->distanceKm($team->location, $alert->location) : null;

        return [
            'id' => $alert->id,
            'urgency' => $alert->urgency->value,
            'urgency_label' => $alert->urgency === BreakdownUrgency::Urgent ? 'Urgent · passagers à bord' : 'Normal · bus à vide',
            'status' => $alert->status->value,
            'reported_at' => $alert->created_at->toIso8601String(),
            'bus' => $alert->bus?->number,
            'type' => $alert->type->value,
            'type_label' => $alert->type->label(),
            'details' => $alert->details,
            'place' => $alert->place_label ?? $alert->mission->boardingPoint->commune.' → '.($alert->mission->direction->value === 'aller' ? 'lieu de l\'événement' : 'retour'),
            'latitude' => $alert->location?->getLatitude(),
            'longitude' => $alert->location?->getLongitude(),
            'passengers_on_board' => $alert->passengers_on_board,
            'distance_km' => $distance,
            'eta_minutes' => $distance !== null ? (int) ceil($distance / self::SPEED_KMH * 60) : null,
            'taken_by' => $alert->techTeam?->code,
            'taken_by_me' => $alert->tech_team_id === $team->id,
            'intervention_id' => $alert->intervention?->id,
            'reporter' => ['name' => $alert->reporter->shortName(), 'phone' => $alert->reporter->phone],
            'photos' => $alert->photoUrls(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentIntervention(Intervention $intervention): array
    {
        $alert = $intervention->alert;

        return [
            'id' => $intervention->id,
            'number' => $intervention->number,
            'step' => $intervention->step->value,
            'steps' => collect(InterventionStep::cases())->map(fn (InterventionStep $step) => [
                'step' => $step->value,
                'label' => $step->label(),
                'at' => $intervention->{$step->timestampColumn()}?->toIso8601String(),
            ])->values(),
            'result' => $intervention->result?->value,
            'towing_requested' => $intervention->towing_requested,
            'diagnosis' => $intervention->diagnosis,
            'replacement_bus' => $intervention->replacementBus?->number,
            'alert' => [
                'id' => $alert->id,
                'bus' => $alert->bus?->number,
                'type_label' => $alert->type->label(),
                'details' => $alert->details,
                'passengers_on_board' => $alert->passengers_on_board,
                'latitude' => $alert->location?->getLatitude(),
                'longitude' => $alert->location?->getLongitude(),
                'reporter' => ['name' => $alert->reporter->shortName(), 'phone' => $alert->reporter->phone],
            ],
        ];
    }

    private function technician(Request $request): Staff
    {
        $staff = $request->user('sanctum');
        abort_unless($staff instanceof Staff, 403);

        return $staff;
    }

    private function teamOf(Request $request): TechTeam
    {
        $team = TechTeam::query()->withoutGlobalScopes()->find($this->technician($request)->tech_team_id);

        if ($team === null) {
            throw new ApiException('Vous n\'êtes rattaché à aucune équipe technique.', 'NO_TEAM', 403);
        }

        return $team;
    }

    private function ensureTeam(Request $request, Intervention $intervention): void
    {
        if ($intervention->tech_team_id !== $this->teamOf($request)->id) {
            throw new ApiException('Cette intervention appartient à une autre équipe.', 'INTERVENTION_OTHER_TEAM', 403);
        }
    }
}
