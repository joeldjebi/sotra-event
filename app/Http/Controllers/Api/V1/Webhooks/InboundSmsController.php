<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Enums\BreakdownType;
use App\Enums\MissionStatus;
use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Staff;
use App\Services\Operations\BreakdownService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Alerte panne en mode dégradé par SMS (CV-19), quand le convoyeur n'a pas de données mobiles.
 * Format : « PANNE [n° de bus] TYPE [précisions] », ex. « PANNE 27 MOTEUR voyant rouge ».
 * Types : MOTEUR, PNEU, FREINS, ELECTRIQUE, ACCIDENT, AUTRE. Le numéro de bus peut être omis.
 *
 * @tags Webhooks
 */
class InboundSmsController extends Controller
{
    /**
     * SMS reçu par la passerelle (secret partagé dans l'en-tête X-Sms-Secret).
     *
     * @unauthenticated
     */
    public function __invoke(Request $request, BreakdownService $breakdowns): JsonResponse
    {
        abort_unless(hash_equals(self::secret(), (string) $request->header('X-Sms-Secret')), 401);

        $validated = $request->validate(['from' => ['required', 'string'], 'text' => ['required', 'string', 'max:500']]);

        if (! PhoneNumber::isValid($validated['from'])
            || preg_match('/^\s*PANNE(?:\s+(\d{1,5}))?\s+(MOTEUR|PNEU|FREINS|ELECTRIQUE|ÉLECTRIQUE|ACCIDENT|AUTRE)\b\s*(.*)$/iu', $validated['text'], $parts) !== 1) {
            return response()->json(['handled' => false, 'reason' => 'format']);
        }

        $staff = Staff::query()->withoutGlobalScopes()->where('phone', PhoneNumber::parse($validated['from'])->e164)
            ->where('role', StaffRole::Conveyor)->where('is_active', true)->first();

        $mission = $staff === null ? null : Mission::query()->withoutGlobalScopes()
            ->with('bus')
            ->where('conveyor_id', $staff->id)
            ->whereIn('status', [MissionStatus::Boarding, MissionStatus::OnRoute, MissionStatus::Planned])
            ->when($parts[1] !== '', fn ($query) => $query->whereHas('bus', fn ($bus) => $bus->withoutGlobalScopes()->where('number', ltrim($parts[1], '0') ?: '0')->orWhere('number', $parts[1])))
            ->orderByRaw("case status when 'en_route' then 0 when 'embarquement' then 1 else 2 end")
            ->first();

        if ($staff === null || $mission === null) {
            return response()->json(['handled' => false, 'reason' => 'mission']);
        }

        $type = match (mb_strtoupper($parts[2])) {
            'MOTEUR' => BreakdownType::Engine,
            'PNEU' => BreakdownType::Tyre,
            'FREINS' => BreakdownType::Brakes,
            'ELECTRIQUE', 'ÉLECTRIQUE' => BreakdownType::Electrical,
            'ACCIDENT' => BreakdownType::Accident,
            default => BreakdownType::Other,
        };

        $alert = $breakdowns->report($mission, $staff, $type, trim($parts[3]) ?: null, null, null, [], 'sms');

        return response()->json(['handled' => true, 'alert_id' => $alert->id]);
    }

    public static function secret(): string
    {
        $secret = config('eventtrans.sms.inbound_secret');

        return is_string($secret) && $secret !== '' ? $secret : hash('sha256', 'sms-inbound|'.config('app.key'));
    }
}
