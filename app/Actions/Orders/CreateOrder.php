<?php

namespace App\Actions\Orders;

use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\OrderStatus;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Enums\SalesChannel;
use App\Exceptions\ApiException;
use App\Models\BoardingPoint;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Services\Sales\CapacityService;
use App\Services\Sales\PricingService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crée une commande et bloque les places pendant le paiement (SP-04 à SP-08, RG-08).
 *
 * Les réservations concurrentes d'une même offre sont sérialisées par un verrou de ligne :
 * jamais de vente au-delà de la capacité, même sous forte charge (NF-05).
 */
class CreateOrder
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CapacityService $capacity,
    ) {}

    public function handle(
        Customer $buyer,
        Event $event,
        int $boardingPointId,
        Offer $offer,
        PassType $type,
        int $quantity,
        SalesChannel $channel,
        ?string $beneficiaryPhone = null,
    ): Order {
        $this->ensureOnSale($event, $channel);

        $point = BoardingPoint::query()->where('event_id', $event->id)->find($boardingPointId)
            ?? throw new ApiException('Point d\'embarquement inconnu pour cet événement.', 'BOARDING_POINT_NOT_FOUND', 404);

        if ($point->cancelled_at !== null) {
            throw new ApiException('Ce départ est annulé.', 'DEPARTURE_CANCELLED', 409);
        }

        $max = (int) config('eventtrans.sales.max_passes_per_order');

        if ($quantity < 1 || $quantity > $max) {
            throw new ApiException("Vous pouvez commander de 1 à {$max} Pass.", 'QUANTITY_OUT_OF_RANGE');
        }

        $holder = $buyer;

        if ($beneficiaryPhone !== null && $beneficiaryPhone !== '') {
            $phone = PhoneNumber::parse($beneficiaryPhone);
            $holder = Customer::query()->firstOrCreate(['phone' => $phone->e164]);
        }

        $quote = $this->pricing->quote($event, $point, $offer, $type, $quantity);

        return DB::transaction(function () use ($buyer, $holder, $event, $point, $offer, $type, $quantity, $channel, $quote): Order {
            if ($this->capacity->lockOffer($event, $offer) === null) {
                throw new ApiException("L'offre {$offer->label()} n'est pas proposée pour cet événement.", 'OFFER_UNAVAILABLE');
            }

            $remaining = $this->capacity->remaining($event, $offer, $type);

            if ($remaining === null) {
                throw new ApiException('La capacité de cette offre n\'est pas encore définie.', 'OFFER_UNAVAILABLE');
            }

            if ($remaining < $quantity) {
                throw new ApiException(
                    $remaining === 0 ? "Plus de place en {$offer->label()} pour cet événement." : "Il ne reste que {$remaining} place(s) en {$offer->label()}.",
                    'CAPACITY_REACHED',
                    409,
                );
            }

            $order = Order::query()->create([
                'reference' => $this->reference(),
                // L'acceptation des CGV est vérifiée avant l'appel (case du site, champ accept_terms de l'API).
                'terms_accepted_at' => now(),
                'terms_version' => (string) config('eventtrans.legal.terms_version'),
                'customer_id' => $buyer->id,
                'event_id' => $event->id,
                'boarding_point_id' => $point->id,
                'offer' => $offer,
                'pass_type' => $type,
                'quantity' => $quantity,
                'beneficiary_phone' => $holder->is($buyer) ? null : $holder->phone,
                'unit_price' => $quote->unitPrice,
                'subtotal' => $quote->subtotal(),
                'fee_rate' => $quote->feeRate,
                'fee_amount' => $quote->fee(),
                'commission_rate' => $quote->commissionRate,
                'commission_amount' => $quote->commission(),
                'company_amount' => $quote->companyAmount(),
                'total' => $quote->total(),
                'channel' => $channel,
                'status' => OrderStatus::PendingPayment,
                'expires_at' => now()->addMinutes((int) config('eventtrans.sales.hold_minutes')),
            ]);

            for ($i = 0; $i < $quantity; $i++) {
                $order->passes()->create([
                    'event_id' => $event->id,
                    'boarding_point_id' => $point->id,
                    'holder_id' => $holder->id,
                    'offer' => $offer,
                    'pass_type' => $type,
                    'status' => PassStatus::PendingPayment,
                    'price' => $quote->unitPrice,
                    'fee_amount' => $quote->unitFee,
                    'commission_amount' => $quote->unitCommission,
                    'company_amount' => $quote->unitCompanyAmount,
                ]);
            }

            return $order;
        });
    }

    private function ensureOnSale(Event $event, SalesChannel $channel): void
    {
        if ($event->status !== EventStatus::OnSale || $event->ends_at->isPast()) {
            throw new ApiException('Cet événement n\'est pas en vente.', 'EVENT_NOT_ON_SALE');
        }

        if (! $event->hasChannel($channel)) {
            throw new ApiException('La vente n\'est pas ouverte sur ce canal.', 'CHANNEL_CLOSED');
        }
    }

    private function reference(): string
    {
        do {
            $reference = 'CMD-'.Str::upper(Str::random(8));
        } while (Order::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
