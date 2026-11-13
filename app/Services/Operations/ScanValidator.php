<?php

namespace App\Services\Operations;

use App\Enums\Direction;
use App\Enums\MissionStatus;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\ScanReason;
use App\Enums\ScanResult;
use App\Enums\Segment;
use App\Models\Mission;
use App\Models\Pass;
use App\Models\Scan;
use App\Models\Staff;
use App\Services\Sales\PassTokenService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Validation d'un scan par le convoyeur (CV-06 à CV-10, RG-03, RG-05, RG-09, NF-01).
 *
 * Vert : Pass valide pour ce bus. Orange (montée autorisée) : valide mais prévu dans un autre bus ou à un
 * autre point. Rouge : refusé, avec un motif. Chaque segment ne se scanne qu'une fois.
 *
 * Le même traitement s'applique aux scans en ligne et aux scans faits hors ligne puis synchronisés :
 * - idempotence par client_uuid (un scan renvoyé n'est enregistré qu'une fois) ;
 * - si deux appareils ont scanné le même segment hors ligne, le plus ancien l'emporte et l'autre est
 *   signalé en conflit (copie de QR suspectée).
 */
class ScanValidator
{
    private const CODE_PATTERN = '/^ET-\d{4}-\d{4}-[A-Z]{3}$/';

    public function __construct(private readonly PassTokenService $tokens) {}

    public function validate(Mission $mission, Staff $conveyor, string $input, string $clientUuid, ?Carbon $scannedAt = null, bool $offline = false, ?int $deviceId = null): Scan
    {
        $existing = Scan::query()->where('client_uuid', $clientUuid)->first();

        if ($existing !== null) {
            return $existing;
        }

        $segment = Segment::fromDirection($mission->direction);
        $scannedAt ??= now();
        $input = trim($input);

        return DB::transaction(function () use ($mission, $conveyor, $input, $clientUuid, $scannedAt, $offline, $deviceId, $segment): Scan {
            [$pass, $result, $reason, $segment] = $this->decide($mission, $input, $segment, $scannedAt, $offline);

            $scan = Scan::query()->create([
                'client_uuid' => $clientUuid,
                'pass_id' => $pass?->id,
                'mission_id' => $mission->id,
                'staff_id' => $conveyor->id,
                'device_id' => $deviceId,
                'segment' => $segment,
                'input' => mb_substr($input, 0, 512),
                'result' => $result,
                'reason' => $reason,
                'offline' => $offline,
                'scanned_at' => $scannedAt,
            ]);

            if ($pass !== null && $result->accepted() && $segment !== Segment::Control) {
                $this->board($pass, $mission, $segment);
            }

            if ($mission->status === MissionStatus::Planned) {
                $mission->update(['status' => MissionStatus::Boarding]);
            }

            return $scan;
        });
    }

    /**
     * @return array{0: Pass|null, 1: ScanResult, 2: ScanReason|null, 3: Segment}
     */
    private function decide(Mission $mission, string $input, Segment $segment, Carbon $scannedAt, bool $offline): array
    {
        [$pass, $result, $reason] = $this->evaluate($mission, $input, $segment, $scannedAt, $offline);

        return [$pass, $result, $reason, $this->controlSegment ?? $segment];
    }

    /** Segment « contrôle » retenu pour un passager transféré depuis un bus en panne. */
    private ?Segment $controlSegment = null;

    /**
     * @return array{0: Pass|null, 1: ScanResult, 2: ScanReason|null}
     */
    private function evaluate(Mission $mission, string $input, Segment $segment, Carbon $scannedAt, bool $offline): array
    {
        $this->controlSegment = null;
        $pass = null;

        if (str_starts_with($input, 'ET1.')) {
            $payload = $this->tokens->verify($input);

            if ($payload === null) {
                return [null, ScanResult::Red, ScanReason::Forged];
            }

            $pass = Pass::query()->lockForUpdate()->find($payload['p']);

            if ($pass !== null && $pass->token_version !== (int) $payload['v']) {
                return [$pass, ScanResult::Red, ScanReason::Outdated];
            }
        } elseif (preg_match(self::CODE_PATTERN, strtoupper($input)) === 1) {
            $pass = Pass::query()->lockForUpdate()->where('code', strtoupper($input))->first();
        }

        if ($pass === null) {
            return [null, ScanResult::Red, ScanReason::UnknownCode];
        }

        if ($pass->event_id !== $mission->event_id) {
            return [$pass, ScanResult::Red, ScanReason::OtherEvent];
        }

        if ($pass->status === PassStatus::PendingPayment || $pass->status === PassStatus::Expired) {
            return [$pass, ScanResult::Red, ScanReason::NotPaid];
        }

        if (in_array($pass->status, [PassStatus::Cancelled, PassStatus::Refunded], true)) {
            return [$pass, ScanResult::Red, ScanReason::Cancelled];
        }

        if (($segment === Segment::Outbound && ! $pass->includesOutbound()) || ($segment === Segment::Return && ! $pass->includesReturn())) {
            return [$pass, ScanResult::Red, ScanReason::SegmentNotPurchased];
        }

        if ($pass->offer !== $mission->offer) {
            return [$pass, ScanResult::Red, ScanReason::OfferMismatch];
        }

        // Bus de remplacement après une panne (RG-20) : un passager déjà monté dans le bus en panne passe en
        // « scan de contrôle », qui ne consomme pas de segment.
        $replaced = Mission::query()->withoutGlobalScopes()->where('replaced_by_mission_id', $mission->id)->first();

        if ($replaced !== null && $replaced->scans()->where('pass_id', $pass->id)->whereIn('result', [ScanResult::Green, ScanResult::Orange])->exists()) {
            $this->controlSegment = Segment::Control;
            $alreadyControlled = $mission->scans()->where('pass_id', $pass->id)->where('segment', Segment::Control)
                ->whereIn('result', [ScanResult::Green, ScanResult::Orange])->exists();

            return $alreadyControlled ? [$pass, ScanResult::Red, ScanReason::AlreadyUsed] : [$pass, ScanResult::Green, null];
        }

        $previous = Scan::query()
            ->where('pass_id', $pass->id)
            ->where('segment', $segment)
            ->whereIn('result', [ScanResult::Green, ScanResult::Orange])
            ->where('conflict', false)
            ->orderBy('scanned_at')
            ->first();

        if ($previous !== null) {
            if ($offline && $scannedAt->lt($previous->scanned_at)) {
                // Deux scans hors ligne du même segment : le plus ancien l'emporte, l'autre est signalé.
                $previous->update(['conflict' => true, 'reason' => ScanReason::AlreadyUsed]);
            } else {
                if ($offline) {
                    return [$pass, ScanResult::Red, ScanReason::AlreadyUsed];
                }

                return [$pass, ScanResult::Red, ScanReason::AlreadyUsed];
            }
        }

        if (! $offline && $mission->onBoardCount() >= $mission->capacity()) {
            return [$pass, ScanResult::Red, ScanReason::BusFull];
        }

        if ($pass->boarding_point_id !== $mission->boarding_point_id) {
            return [$pass, ScanResult::Orange, ScanReason::OtherPoint];
        }

        if ($segment === Segment::Outbound && $pass->outbound_mission_id !== null && $pass->outbound_mission_id !== $mission->id) {
            return [$pass, ScanResult::Orange, ScanReason::OtherBus];
        }

        return [$pass, ScanResult::Green, null];
    }

    /**
     * Met à jour le Pass après une montée acceptée : statut du segment et bus réellement pris.
     */
    private function board(Pass $pass, Mission $mission, Segment $segment): void
    {
        $next = match (true) {
            $pass->pass_type !== PassType::RoundTrip => PassStatus::Completed,
            $segment === Segment::Outbound => $pass->status === PassStatus::ReturnUsed ? PassStatus::Completed : PassStatus::OutboundUsed,
            default => $pass->status === PassStatus::OutboundUsed ? PassStatus::Completed : PassStatus::ReturnUsed,
        };

        $attributes = $segment === Segment::Outbound
            ? ['outbound_mission_id' => $mission->id]
            : ['return_mission_id' => $mission->id];

        if ($pass->status !== $next && $pass->status->canTransitionTo($next)) {
            $attributes['status'] = $next;
        }

        $pass->update($attributes);
    }

    /**
     * Libellé du passager affiché au convoyeur (« Aïcha K. ») : jamais son numéro complet.
     */
    public static function passengerName(Pass $pass): string
    {
        $holder = $pass->holder;

        if ($holder->first_name) {
            return trim($holder->first_name.' '.($holder->last_name ? mb_substr($holder->last_name, 0, 1).'.' : ''));
        }

        return 'Passager ··'.substr($holder->phone, -2);
    }

    public static function directionOf(Segment $segment): Direction
    {
        return $segment === Segment::Return ? Direction::Return : Direction::Outbound;
    }
}
