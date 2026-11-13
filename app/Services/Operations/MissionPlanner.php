<?php

namespace App\Services\Operations;

use App\Enums\Direction;
use App\Enums\EventStatus;
use App\Enums\MissionStatus;
use App\Enums\Offer;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Models\AuditLog;
use App\Models\BoardingPoint;
use App\Models\Event;
use App\Models\EventOffer;
use App\Models\Mission;
use App\Models\Pass;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plan de transport (RG-08, RG-05) : après les ventes, le nombre de bus à envoyer par point et par offre
 * découle de la demande (bus nécessaires = ⌈Pass vendus ÷ places par bus⌉), sens par sens.
 *
 * - Les Pass aller sont affectés à une mission dans l'ordre d'achat, une commande restant groupée
 *   dans le même bus quand c'est possible (liste « Attendus » du convoyeur).
 * - Au retour, le passager monte dans n'importe quel bus de son point et de son offre (RG-05) :
 *   seul le nombre de missions retour est calculé.
 * - Relancer le plan est sans risque : il ajoute les missions manquantes, retire seulement les missions
 *   vides et sans équipage, et n'affecte que les Pass qui ne le sont pas encore.
 */
class MissionPlanner
{
    /**
     * @return array{created: int, removed: int, assigned: int, warnings: list<string>}
     */
    public function plan(Event $event): array
    {
        if (! in_array($event->status, [EventStatus::Accepted, EventStatus::OnSale], true)) {
            throw ValidationException::withMessages(['plan' => 'Le plan de transport se génère pour un événement accepté ou en vente.']);
        }

        $result = DB::transaction(function () use ($event): array {
            Event::query()->withoutGlobalScopes()->whereKey($event->id)->lockForUpdate()->first();

            $created = 0;
            $removed = 0;
            $warnings = [];
            $offers = $event->offers()->get()->keyBy(fn (EventOffer $offer) => $offer->offer->value);

            foreach ($this->demand($event) as $row) {
                /** @var EventOffer|null $eventOffer */
                $eventOffer = $offers->get($row['offer']->value);

                if ($eventOffer?->seats_per_bus === null) {
                    $warnings[] = "{$row['offer']->label()} : places par bus non définies (Plan de transport, capacité).";

                    continue;
                }

                $needed = (int) ceil($row['passengers'] / $eventOffer->seats_per_bus);
                [$added, $deleted] = $this->syncMissions($event, $row['point'], $row['offer'], $row['direction'], $needed, $eventOffer->seats_per_bus);
                $created += $added;
                $removed += $deleted;
            }

            foreach ($offers as $eventOffer) {
                foreach (Direction::cases() as $direction) {
                    $count = $event->missions()->where('offer', $eventOffer->offer)->where('direction', $direction)->count();

                    if ($eventOffer->bus_count !== null && $count > $eventOffer->bus_count) {
                        $warnings[] = "{$eventOffer->offer->label()} · {$direction->label()} : {$count} bus nécessaires pour {$eventOffer->bus_count} prévus.";
                    }
                }
            }

            return ['created' => $created, 'removed' => $removed, 'warnings' => $warnings];
        });

        $assigned = $this->assignPending($event);

        AuditLog::record('transport_plan_generated', $event, [], ['created' => $result['created'], 'removed' => $result['removed'], 'assigned' => $assigned]);

        return $result + ['assigned' => $assigned];
    }

    /**
     * Affecte aux missions aller les Pass vendus qui n'en ont pas encore (achats après la génération).
     * Ne fait rien tant que le plan n'a pas été généré pour ce point et cette offre.
     */
    public function assignPending(Event $event): int
    {
        return DB::transaction(function () use ($event): int {
            $assigned = 0;

            $groups = Pass::query()
                ->where('passes.event_id', $event->id)
                ->whereIn('passes.status', PassStatus::sold())
                ->whereIn('passes.pass_type', [PassType::RoundTrip, PassType::Outbound])
                ->whereNull('passes.outbound_mission_id')
                ->join('orders', 'orders.id', '=', 'passes.order_id')
                ->orderBy('orders.paid_at')
                ->orderBy('passes.id')
                ->select('passes.*')
                ->lock('for update of passes')
                ->get()
                ->groupBy(fn (Pass $pass) => $pass->boarding_point_id.'|'.$pass->offer->value);

            foreach ($groups as $passes) {
                /** @var Pass $first */
                $first = $passes->first();
                $missions = $this->missions($event, $first->boarding_point_id, $first->offer, Direction::Outbound);

                if ($missions->isEmpty()) {
                    continue;
                }

                foreach ($passes->groupBy('order_id') as $orderPasses) {
                    $assigned += $this->place($event, $missions, $orderPasses);
                }
            }

            return $assigned;
        });
    }

    /**
     * Demande par point, offre et sens : nombre de Pass vendus qui utilisent ce segment.
     *
     * @return list<array{point: BoardingPoint, offer: Offer, direction: Direction, passengers: int}>
     */
    public function demand(Event $event): array
    {
        $counts = Pass::query()
            ->where('event_id', $event->id)
            ->whereIn('status', PassStatus::sold())
            ->selectRaw('boarding_point_id, offer, pass_type, count(*) as total')
            ->groupBy('boarding_point_id', 'offer', 'pass_type')
            ->get();

        $rows = [];

        foreach ($event->boardingPoints()->get() as $point) {
            foreach ($event->loadMissing('offers')->activeOffers() as $offer) {
                foreach (Direction::cases() as $direction) {
                    $types = $direction === Direction::Outbound
                        ? [PassType::RoundTrip->value, PassType::Outbound->value]
                        : [PassType::RoundTrip->value, PassType::Return->value];

                    $passengers = (int) $counts
                        ->filter(fn (Pass $count) => $count->boarding_point_id === $point->id
                            && $count->offer === $offer
                            && in_array($count->pass_type->value, $types, true))
                        ->sum('total');

                    $rows[] = ['point' => $point, 'offer' => $offer, 'direction' => $direction, 'passengers' => $passengers];
                }
            }
        }

        return $rows;
    }

    /**
     * @return array{0: int, 1: int} missions ajoutées, missions retirées
     */
    private function syncMissions(Event $event, BoardingPoint $point, Offer $offer, Direction $direction, int $needed, int $seats): array
    {
        $missions = $this->missions($event, $point->id, $offer, $direction);
        $added = 0;
        $removed = 0;

        for ($sequence = (int) ($missions->max('sequence') ?? 0) + 1; $missions->count() + $added < $needed; $sequence++) {
            $this->createMission($event, $point, $offer, $direction, $sequence, $seats);
            $added++;
        }

        if ($missions->count() > $needed) {
            $surplus = $missions->count() - $needed;

            foreach ($missions->sortByDesc('sequence') as $mission) {
                if ($surplus === 0) {
                    break;
                }

                if ($mission->bus_id === null && $mission->driver_id === null && $mission->conveyor_id === null
                    && ! $mission->outboundPasses()->exists()) {
                    $mission->delete();
                    $surplus--;
                    $removed++;
                }
            }
        }

        return [$added, $removed];
    }

    /**
     * Place les Pass d'une commande : dans un seul bus s'il a la place, sinon répartis, et une mission
     * supplémentaire est créée si tous les bus sont pleins.
     *
     * @param  Collection<int, Mission>  $missions
     * @param  Collection<int, Pass>  $passes
     */
    private function place(Event $event, Collection $missions, Collection $passes): int
    {
        $free = fn (Mission $mission): int => $mission->seats - $mission->outboundPasses()->count();
        $target = $missions->first(fn (Mission $mission) => $free($mission) >= $passes->count());

        if ($target !== null) {
            Pass::query()->whereIn('id', $passes->pluck('id'))->update(['outbound_mission_id' => $target->id]);

            return $passes->count();
        }

        $placed = 0;

        foreach ($passes as $pass) {
            $mission = $missions->first(fn (Mission $mission) => $free($mission) > 0);

            if ($mission === null) {
                /** @var Mission $last */
                $last = $missions->sortByDesc('sequence')->first();
                $mission = $this->createMission($event, $last->boardingPoint, $last->offer, Direction::Outbound, $last->sequence + 1, $last->seats);
                $missions->push($mission);
            }

            $pass->update(['outbound_mission_id' => $mission->id]);
            $placed++;
        }

        return $placed;
    }

    /**
     * @return Collection<int, Mission>
     */
    private function missions(Event $event, int $pointId, Offer $offer, Direction $direction): Collection
    {
        return Mission::query()->withoutGlobalScopes()
            ->with('boardingPoint')
            ->where('event_id', $event->id)
            ->where('boarding_point_id', $pointId)
            ->where('offer', $offer)
            ->where('direction', $direction)
            ->orderBy('sequence')
            ->get();
    }

    private function createMission(Event $event, BoardingPoint $point, Offer $offer, Direction $direction, int $sequence, int $seats): Mission
    {
        return Mission::query()->withoutGlobalScopes()->create([
            'event_id' => $event->id,
            'company_id' => $event->company_id,
            'boarding_point_id' => $point->id,
            'offer' => $offer,
            'direction' => $direction,
            'sequence' => $sequence,
            'seats' => $seats,
            'planned_departure_at' => $direction === Direction::Outbound ? $point->departure_at : $point->return_departure_at,
            'status' => MissionStatus::Planned,
        ]);
    }
}
