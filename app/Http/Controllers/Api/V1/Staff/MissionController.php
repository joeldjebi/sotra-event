<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\Direction;
use App\Enums\MissionStatus;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Events\MissionUpdated;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MissionResource;
use App\Models\Mission;
use App\Models\Pass;
use App\Models\Scan;
use App\Models\Staff;
use App\Services\Operations\DelayMonitor;
use App\Services\Operations\ScanValidator;
use App\Services\Operations\TripTracker;
use App\Services\Sales\PassTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * App convoyeur : missions du jour, manifeste hors ligne, étapes de la mission, positions GPS (CV-01 à CV-05, CV-11 à CV-14).
 *
 * @tags Convoyeur
 */
class MissionController extends Controller
{
    /**
     * Missions du convoyeur pour une journée (aujourd'hui par défaut).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $day = $request->filled('date') ? Carbon::parse($request->string('date')->toString()) : now();

        $missions = Mission::query()
            ->with(['event.venue', 'boardingPoint', 'bus.depot', 'driver'])
            ->where('conveyor_id', $this->staff($request)->id)
            ->whereBetween('planned_departure_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()->addHours(6)])
            ->orderBy('planned_departure_at')
            ->get();

        return MissionResource::collection($missions);
    }

    /**
     * Manifeste pour le contrôle hors ligne : Pass de l'événement pour l'offre du bus (avec la version du QR),
     * Pass attendus dans ce bus, Pass refusés (annulés, remboursés) et clé publique de vérification des QR.
     */
    public function manifest(Request $request, Mission $mission, PassTokenService $tokens): JsonResponse
    {
        $this->ensureConveyor($request, $mission);
        $outbound = $mission->direction === Direction::Outbound;

        $passes = Pass::query()
            ->with('holder')
            ->where('event_id', $mission->event_id)
            ->where('offer', $mission->offer)
            ->whereIn('pass_type', $outbound ? [PassType::RoundTrip, PassType::Outbound] : [PassType::RoundTrip, PassType::Return])
            ->whereIn('status', [...PassStatus::sold(), PassStatus::Cancelled, PassStatus::Refunded])
            ->orderBy('id')
            ->get();

        $usedSegment = $outbound ? [PassStatus::OutboundUsed, PassStatus::Completed] : [PassStatus::ReturnUsed, PassStatus::Completed];

        $rows = $passes->map(fn (Pass $pass) => [
            'id' => $pass->id,
            'code' => $pass->code,
            'token_version' => $pass->token_version,
            'pass_type' => $pass->pass_type->value,
            'boarding_point_id' => $pass->boarding_point_id,
            'passenger' => ScanValidator::passengerName($pass),
            'status' => $pass->status->value,
            'revoked' => in_array($pass->status, [PassStatus::Cancelled, PassStatus::Refunded], true),
            'segment_used' => in_array($pass->status, $usedSegment, true),
            'expected' => $outbound
                ? $pass->outbound_mission_id === $mission->id
                : $pass->boarding_point_id === $mission->boarding_point_id,
        ])->values();

        return response()->json(['data' => [
            'mission' => new MissionResource($mission->load(['event.venue', 'boardingPoint', 'bus.depot', 'driver'])),
            'public_key' => $tokens->publicKeyBase64(),
            'generated_at' => now()->toIso8601String(),
            'version' => sha1((string) json_encode($rows)),
            'passes' => $rows,
        ]]);
    }

    /**
     * Liste d'embarquement : passagers embarqués et attendus (CV-12, CV-13).
     */
    public function boarding(Request $request, Mission $mission): JsonResponse
    {
        $this->ensureConveyor($request, $mission);

        $boarded = Scan::query()->with('pass.holder')->where('mission_id', $mission->id)
            ->whereIn('result', ['vert', 'orange'])->where('conflict', false)->orderBy('scanned_at')->get();
        $boardedIds = $boarded->pluck('pass_id')->filter()->all();

        $expected = $mission->direction === Direction::Outbound
            ? Pass::query()->with('holder')->where('outbound_mission_id', $mission->id)->whereIn('status', PassStatus::sold())->whereNotIn('id', $boardedIds)->get()
            : collect();

        return response()->json(['data' => [
            'capacity' => $mission->capacity(),
            'on_board' => $boarded->count(),
            'boarded' => $boarded->map(fn (Scan $scan) => [
                'passenger' => $scan->pass ? ScanValidator::passengerName($scan->pass) : null,
                'offer' => $scan->pass?->offer->value,
                'scanned_at' => $scan->scanned_at->toIso8601String(),
                'result' => $scan->result->value,
            ])->values(),
            'expected' => $expected->map(fn (Pass $pass) => [
                'passenger' => ScanValidator::passengerName($pass),
                'offer' => $pass->offer->value,
                'code' => $pass->code,
            ])->values(),
        ]]);
    }

    /**
     * Passer à l'embarquement.
     */
    public function boardingStarted(Request $request, Mission $mission): MissionResource
    {
        return $this->transition($request, $mission, [MissionStatus::Planned], MissionStatus::Boarding);
    }

    /**
     * Confirmer le départ du bus (CV-14) : heure réelle enregistrée pour la ponctualité, mission « En route ».
     */
    public function depart(Request $request, Mission $mission, DelayMonitor $delays): MissionResource
    {
        $resource = $this->transition($request, $mission, [MissionStatus::Planned, MissionStatus::Boarding], MissionStatus::OnRoute, ['actual_departure_at' => now()]);
        $delays->check($mission);

        return $resource;
    }

    /**
     * Confirmer l'arrivée (si la détection GPS n'a pas suffi).
     */
    public function arrive(Request $request, Mission $mission): MissionResource
    {
        return $this->transition($request, $mission, [MissionStatus::OnRoute], MissionStatus::Arrived, ['arrived_at' => now()]);
    }

    /**
     * Envoyer des positions GPS par lot (toutes les 15 s pendant le trajet, mises en file hors connexion).
     */
    public function positions(Request $request, Mission $mission, TripTracker $tracker): JsonResponse
    {
        $this->ensureConveyor($request, $mission);

        $validated = $request->validate([
            'positions' => ['required', 'array', 'min:1', 'max:500'],
            'positions.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'positions.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'positions.*.recorded_at' => ['required', 'date'],
            'positions.*.accuracy_m' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'positions.*.speed_kmh' => ['nullable', 'numeric', 'min:0', 'max:200'],
        ]);

        $stored = $tracker->record($mission, $validated['positions']);

        return response()->json(['data' => ['stored' => $stored, 'status' => $mission->fresh()?->status->value]]);
    }

    /**
     * @param  list<MissionStatus>  $from
     * @param  array<string, mixed>  $extra
     */
    private function transition(Request $request, Mission $mission, array $from, MissionStatus $to, array $extra = []): MissionResource
    {
        $this->ensureConveyor($request, $mission);

        if (! in_array($mission->status, $from, true)) {
            throw new ApiException("Action impossible : la mission est « {$mission->status->label()} ».", 'MISSION_STATUS_INVALID', 409);
        }

        $mission->update(['status' => $to] + $extra);
        MissionUpdated::dispatch($mission);

        return new MissionResource($mission->load(['event.venue', 'boardingPoint', 'bus.depot', 'driver']));
    }

    private function staff(Request $request): Staff
    {
        $staff = $request->user('sanctum');
        abort_unless($staff instanceof Staff, 403);

        return $staff;
    }

    private function ensureConveyor(Request $request, Mission $mission): void
    {
        if ($mission->conveyor_id !== $this->staff($request)->id) {
            throw new ApiException('Cette mission ne vous est pas affectée.', 'MISSION_NOT_ASSIGNED', 403);
        }
    }
}
