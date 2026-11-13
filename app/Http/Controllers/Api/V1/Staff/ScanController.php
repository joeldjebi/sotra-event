<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Events\MissionUpdated;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Mission;
use App\Models\Scan;
use App\Models\Staff;
use App\Services\Operations\ScanValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Scan des Pass (CV-06 à CV-10) : en ligne, ou par lot pour les scans faits hors connexion (NF-09).
 *
 * @tags Convoyeur
 */
class ScanController extends Controller
{
    public function __construct(private readonly ScanValidator $validator) {}

    /**
     * Valider un Pass en ligne (QR ou code saisi). Réponse : vert, orange ou rouge avec le motif.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mission_id' => ['required', 'integer'],
            'input' => ['required', 'string', 'max:512'],
            'client_uuid' => ['required', 'uuid'],
        ]);

        $mission = $this->mission($request, (int) $validated['mission_id']);
        $scan = $this->validator->validate($mission, $this->staff($request), $validated['input'], $validated['client_uuid'], now(), false, $this->deviceId($request));
        MissionUpdated::dispatch($mission->fresh());

        return response()->json(['data' => $this->present($scan, $mission)]);
    }

    /**
     * Envoyer les scans faits hors connexion. Chaque scan garde son heure ; les doublons (même client_uuid)
     * sont ignorés ; si deux appareils ont scanné le même Pass, le plus ancien l'emporte.
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'scans' => ['required', 'array', 'min:1', 'max:1000'],
            'scans.*.mission_id' => ['required', 'integer'],
            'scans.*.input' => ['required', 'string', 'max:512'],
            'scans.*.client_uuid' => ['required', 'uuid'],
            'scans.*.scanned_at' => ['required', 'date'],
        ]);

        $results = [];
        $missions = [];

        foreach ($request->collect('scans')->sortBy('scanned_at') as $item) {
            $mission = $missions[$item['mission_id']] ??= $this->mission($request, (int) $item['mission_id']);
            $scan = $this->validator->validate($mission, $this->staff($request), $item['input'], $item['client_uuid'], Carbon::parse($item['scanned_at']), true, $this->deviceId($request));
            $results[] = ['client_uuid' => $item['client_uuid']] + $this->present($scan, $mission);
        }

        foreach ($missions as $mission) {
            MissionUpdated::dispatch($mission->fresh());
        }

        return response()->json(['data' => [
            'synced' => count($results),
            'conflicts' => Scan::query()->whereIn('client_uuid', array_column($results, 'client_uuid'))->where('conflict', true)->count(),
            'results' => $results,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Scan $scan, Mission $mission): array
    {
        $scan->loadMissing('pass.holder', 'pass.boardingPoint');

        return [
            'result' => $scan->result->value,
            'reason' => $scan->reason?->value,
            'message' => $scan->reason?->label() ?? 'Pass valide',
            'conflict' => $scan->conflict,
            'pass' => $scan->pass ? [
                'code' => $scan->pass->code,
                'offer' => $scan->pass->offer->value,
                'offer_label' => $scan->pass->offer->label(),
                'boarding_point' => $scan->pass->boardingPoint->commune,
                'passenger' => ScanValidator::passengerName($scan->pass),
            ] : null,
            'on_board' => $mission->onBoardCount(),
            'capacity' => $mission->capacity(),
        ];
    }

    private function mission(Request $request, int $missionId): Mission
    {
        $mission = Mission::query()->with('bus')->find($missionId);

        if ($mission === null || $mission->conveyor_id !== $this->staff($request)->id) {
            throw new ApiException('Cette mission ne vous est pas affectée.', 'MISSION_NOT_ASSIGNED', 403);
        }

        return $mission;
    }

    private function staff(Request $request): Staff
    {
        $staff = $request->user('sanctum');
        abort_unless($staff instanceof Staff, 403);

        return $staff;
    }

    private function deviceId(Request $request): ?int
    {
        $device = $request->attributes->get('device');

        return $device instanceof Device ? $device->id : null;
    }
}
