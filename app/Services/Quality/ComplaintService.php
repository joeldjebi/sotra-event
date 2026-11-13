<?php

namespace App\Services\Quality;

use App\Contracts\SmsSender;
use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\Direction;
use App\Enums\PassStatus;
use App\Exceptions\ApiException;
use App\Models\Complaint;
use App\Models\Mission;
use App\Models\Pass;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Réclamations (SP-23, QU-03) : dépôt par le spectateur, réclamation collective en cas de retard,
 * traitement par la Direction qualité (Ouverte → En cours → Traitée) et réponse par SMS.
 */
class ComplaintService
{
    /** Réclamations ouvertes au plus par Pass, pour éviter les doublons. */
    private const int MAX_OPEN_PER_PASS = 3;

    public function __construct(private readonly SmsSender $sms) {}

    public function open(Pass $pass, ComplaintCategory $category, ?string $details): Complaint
    {
        if (! in_array($pass->status, PassStatus::owned(), true)) {
            throw new ApiException('Ce Pass ne permet pas de déposer une réclamation.', 'COMPLAINT_NOT_ALLOWED', 422);
        }

        $open = $pass->complaints()->where('status', '!=', ComplaintStatus::Resolved)->count();

        if ($open >= self::MAX_OPEN_PER_PASS) {
            throw new ApiException('Vous avez déjà des réclamations en cours pour ce Pass.', 'COMPLAINT_LIMIT', 429);
        }

        $missionId = $pass->scans()->whereIn('result', ['vert', 'orange'])->latest('scanned_at')->value('mission_id')
            ?? ($category === ComplaintCategory::MissedBus ? $pass->return_mission_id : $pass->outbound_mission_id);

        return DB::transaction(fn () => Complaint::query()->create([
            'number' => $this->nextNumber(),
            'event_id' => $pass->event_id,
            'company_id' => $pass->event->company_id,
            'pass_id' => $pass->id,
            'customer_id' => $pass->holder_id,
            'mission_id' => $missionId,
            'category' => $category,
            'title' => $category->label(),
            'details' => $details !== null && trim($details) !== '' ? trim($details) : null,
            'status' => ComplaintStatus::Open,
        ]));
    }

    /**
     * Réclamation collective pour les passagers d'un bus en retard (une seule par mission).
     */
    public function collectiveDelay(Mission $mission, int $delayMinutes): Complaint
    {
        return DB::transaction(function () use ($mission, $delayMinutes) {
            $existing = Complaint::query()->withoutGlobalScopes()
                ->where('mission_id', $mission->id)->where('is_collective', true)->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing;
            }

            $column = $mission->direction === Direction::Outbound ? 'outbound_mission_id' : 'return_mission_id';
            $affected = Pass::query()->where($column, $mission->id)->whereIn('status', PassStatus::sold())->count();
            $bus = $mission->bus ? "Bus {$mission->bus->number}" : "Départ {$mission->boardingPoint->commune}";

            return Complaint::query()->create([
                'number' => $this->nextNumber(),
                'event_id' => $mission->event_id,
                'company_id' => $mission->company_id,
                'mission_id' => $mission->id,
                'category' => ComplaintCategory::Delay,
                'title' => "Retard de {$delayMinutes} min · {$bus} ({$mission->direction->label()})",
                'details' => "Réclamation collective ouverte automatiquement : départ prévu à {$mission->planned_departure_at->format('H\hi')}.",
                'status' => ComplaintStatus::Open,
                'is_collective' => true,
                'affected_count' => $affected,
            ]);
        });
    }

    public function take(Complaint $complaint, User $user): Complaint
    {
        if ($complaint->status === ComplaintStatus::Open) {
            $complaint->update(['status' => ComplaintStatus::InProgress, 'handled_by' => $user->id, 'taken_at' => now()]);
        }

        return $complaint;
    }

    /**
     * Clôture avec une réponse, envoyée par SMS au spectateur (ou aux passagers concernés si collective).
     */
    public function resolve(Complaint $complaint, User $user, string $resolution): Complaint
    {
        $complaint->update([
            'status' => ComplaintStatus::Resolved,
            'resolution' => $resolution,
            'handled_by' => $complaint->handled_by ?? $user->id,
            'taken_at' => $complaint->taken_at ?? now(),
            'resolved_at' => now(),
        ]);

        $body = "EVENT TRANS : réponse à votre réclamation {$complaint->number}. {$resolution}";

        foreach ($this->recipients($complaint) as $phone) {
            $this->sms->send(PhoneNumber::parse($phone), $body);
        }

        return $complaint;
    }

    /**
     * @return list<string>
     */
    private function recipients(Complaint $complaint): array
    {
        if (! $complaint->is_collective) {
            return $complaint->customer ? [$complaint->customer->phone] : [];
        }

        $mission = $complaint->mission;

        if ($mission === null) {
            return [];
        }

        $column = $mission->direction === Direction::Outbound ? 'outbound_mission_id' : 'return_mission_id';

        return Pass::query()->with('holder')->where($column, $mission->id)->whereIn('status', PassStatus::sold())->get()
            ->map(fn (Pass $pass): string => $pass->holder->phone)->unique()->values()->all();
    }

    private function nextNumber(): string
    {
        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ['complaints.number']);
        $last = (int) Complaint::query()->withoutGlobalScopes()->max(DB::raw('cast(substr(number, 4) as integer)'));

        return sprintf('RC-%04d', $last + 1);
    }
}
