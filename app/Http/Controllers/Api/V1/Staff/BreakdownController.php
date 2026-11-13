<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\BreakdownType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Staff;
use App\Services\Operations\BreakdownService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Signaler une panne depuis l'app convoyeur (CV-15 à CV-18).
 *
 * @tags Convoyeur
 */
class BreakdownController extends Controller
{
    /**
     * Envoyer l'alerte au service technique, avec la position GPS et jusqu'à 4 photos.
     */
    public function store(Request $request, BreakdownService $breakdowns): JsonResponse
    {
        $validated = $request->validate([
            'mission_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(BreakdownType::class)],
            'details' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'photos' => ['array', 'max:4'],
            'photos.*' => ['image', 'max:6144'],
        ]);

        $staff = $request->user('sanctum');
        abort_unless($staff instanceof Staff, 403);

        $mission = Mission::query()->find($validated['mission_id']);

        if ($mission === null || $mission->conveyor_id !== $staff->id) {
            throw new ApiException('Cette mission ne vous est pas affectée.', 'MISSION_NOT_ASSIGNED', 403);
        }

        $photos = array_map(fn (UploadedFile $photo) => $photo->store('breakdowns', 'public'), $request->file('photos', []));

        $alert = $breakdowns->report(
            $mission,
            $staff,
            BreakdownType::from($validated['type']),
            $validated['details'] ?? null,
            isset($validated['latitude']) ? Point::makeGeodetic((float) $validated['latitude'], (float) $validated['longitude']) : null,
            $validated['accuracy_m'] ?? null,
            array_values(array_filter($photos)),
        );

        return response()->json(['data' => [
            'id' => $alert->id,
            'urgency' => $alert->urgency->value,
            'urgency_label' => $alert->urgency->label(),
            'passengers_on_board' => $alert->passengers_on_board,
            'status' => $alert->status->value,
            'depot_phone' => $mission->bus?->depot?->phone,
        ]], 201);
    }
}
