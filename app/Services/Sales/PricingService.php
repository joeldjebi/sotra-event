<?php

namespace App\Services\Sales;

use App\Data\Quote;
use App\Enums\Offer;
use App\Enums\PassType;
use App\Exceptions\ApiException;
use App\Models\BoardingPoint;
use App\Models\Event;
use App\Models\EventPrice;

/**
 * Prix d'un Pass et répartition (RG-07, RG-11 à RG-16).
 *
 * Par Pass : frais client = arrondi(prix × taux de frais) ; commission = arrondi(prix × taux de commission) ;
 * part compagnie = prix − commission. Exemple RG-13 : 3 000 → frais 60, total 3 060, commission 300, compagnie 2 700.
 */
class PricingService
{
    public function price(Event $event, BoardingPoint $point, Offer $offer, PassType $type): ?int
    {
        $price = EventPrice::query()
            ->where('event_id', $event->id)
            ->where('zone', $point->zone)
            ->where('offer', $offer)
            ->where('pass_type', $type)
            ->value('price');

        return $price === null ? null : (int) $price;
    }

    public function quote(Event $event, BoardingPoint $point, Offer $offer, PassType $type, int $quantity): Quote
    {
        $price = $this->price($event, $point, $offer, $type);

        if ($price === null) {
            throw new ApiException("Le Pass {$type->label()} {$offer->label()} n'est pas proposé depuis ce point.", 'PRICE_UNAVAILABLE');
        }

        $feeRate = (string) $event->fee_rate;
        $commissionRate = (string) $event->commission_rate;
        $commission = self::percent($price, $commissionRate);

        return new Quote(
            quantity: $quantity,
            unitPrice: $price,
            unitFee: self::percent($price, $feeRate),
            unitCommission: $commission,
            unitCompanyAmount: $price - $commission,
            feeRate: $feeRate,
            commissionRate: $commissionRate,
        );
    }

    /**
     * Pourcentage arrondi à l'unité (RG-16), sans flottant : 3 000 × 2,00 % = 60.
     */
    public static function percent(int $amount, string $rate): int
    {
        $basisPoints = (int) round(((float) $rate) * 100);

        return intdiv($amount * $basisPoints + 5000, 10000);
    }

    /**
     * Prix « dès » d'un événement : le plus bas de la grille (RG-07).
     */
    public function lowestPrice(Event $event): ?int
    {
        $min = EventPrice::query()->where('event_id', $event->id)->min('price');

        return $min === null ? null : (int) $min;
    }
}
