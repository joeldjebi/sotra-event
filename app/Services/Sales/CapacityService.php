<?php

namespace App\Services\Sales;

use App\Enums\Offer;
use App\Enums\OrderStatus;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Models\Event;
use App\Models\EventOffer;
use App\Models\Pass;
use Illuminate\Database\Eloquent\Builder;

/**
 * Capacité restante par offre et par segment (RG-08, RG-05) :
 * capacité de l'offre = bus × places ; un aller-retour occupe une place à l'aller ET au retour ;
 * un Pass « en attente de paiement » occupe sa place tant que sa commande n'a pas expiré.
 *
 * Les réservations concurrentes sont sérialisées par un verrou sur la ligne event_offers (lockOffer).
 */
class CapacityService
{
    /**
     * Places encore disponibles pour un type de Pass (null si la capacité n'est pas définie).
     */
    public function remaining(Event $event, Offer $offer, PassType $type): ?int
    {
        $capacity = $this->capacity($event, $offer);

        if ($capacity === null) {
            return null;
        }

        $outbound = $capacity - $this->occupied($event, $offer, 'outbound');
        $return = $capacity - $this->occupied($event, $offer, 'return');

        return max(0, match ($type) {
            PassType::RoundTrip => min($outbound, $return),
            PassType::Outbound => $outbound,
            PassType::Return => $return,
        });
    }

    /**
     * Verrouille la ligne de capacité de l'offre jusqu'à la fin de la transaction en cours.
     */
    public function lockOffer(Event $event, Offer $offer): ?EventOffer
    {
        return EventOffer::query()->where('event_id', $event->id)->where('offer', $offer)->lockForUpdate()->first();
    }

    public function capacity(Event $event, Offer $offer): ?int
    {
        return EventOffer::query()->where('event_id', $event->id)->where('offer', $offer)->first()?->capacity();
    }

    private function occupied(Event $event, Offer $offer, string $segment): int
    {
        $types = $segment === 'outbound'
            ? [PassType::RoundTrip, PassType::Outbound]
            : [PassType::RoundTrip, PassType::Return];

        return Pass::query()
            ->where('event_id', $event->id)
            ->where('offer', $offer)
            ->whereIn('pass_type', $types)
            ->whereIn('status', PassStatus::holdingSeat())
            ->where(fn (Builder $query) => $query
                ->where('status', '!=', PassStatus::PendingPayment)
                ->orWhereHas('order', fn (Builder $order) => $order
                    ->where('status', OrderStatus::PendingPayment)
                    ->where('expires_at', '>', now())))
            ->count();
    }
}
