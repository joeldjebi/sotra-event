<?php

namespace App\Services\Sales;

use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\PassType;
use App\Enums\SalesChannel;
use App\Models\Event;
use App\Models\EventPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue des événements en vente pour un canal (SP-01 à SP-05) : liste, prix « dès », communes desservies,
 * et pour un événement, ses points et ses offres avec prix et places restantes.
 */
class EventCatalog
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CapacityService $capacity,
    ) {}

    /**
     * @return Builder<Event>
     */
    public function onSale(SalesChannel $channel): Builder
    {
        return Event::query()
            ->withoutGlobalScopes()
            ->where('status', EventStatus::OnSale)
            ->where('ends_at', '>', now())
            ->whereJsonContains('channels', $channel->value);
    }

    /**
     * @return Collection<int, Event>
     */
    public function listing(SalesChannel $channel, ?string $search = null): Collection
    {
        return $this->onSale($channel)
            ->with(['venue'])
            ->withCount(['boardingPoints as communes_count' => fn (Builder $query) => $query->reorder()->select(DB::raw('count(distinct commune_code)'))])
            ->withMin('prices as from_price', 'price')
            ->when($search !== null && trim($search) !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereRaw('lower(events.name) like ?', ['%'.mb_strtolower(trim((string) $search)).'%'])
                ->orWhereHas('venue', fn (Builder $venue) => $venue->whereRaw('lower(name) like ?', ['%'.mb_strtolower(trim((string) $search)).'%']))))
            ->orderByDesc('featured')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Offres d'un événement : prix par type et par zone, places restantes par type.
     *
     * @return list<array{offer: Offer, prices: array<string, array<int, int>>, remaining: array<string, int|null>}>
     */
    public function offers(Event $event): array
    {
        $event->loadMissing(['offers', 'prices']);
        $result = [];

        foreach ($event->activeOffers() as $offer) {
            $prices = [];

            foreach (PassType::cases() as $type) {
                $prices[$type->value] = $event->prices
                    ->filter(fn (EventPrice $price) => $price->offer === $offer && $price->pass_type === $type)
                    ->mapWithKeys(fn (EventPrice $price) => [$price->zone => $price->price])
                    ->all();
            }

            $result[] = [
                'offer' => $offer,
                'prices' => $prices,
                'remaining' => collect(PassType::cases())->mapWithKeys(fn (PassType $type) => [$type->value => $this->capacity->remaining($event, $offer, $type)])->all(),
            ];
        }

        return $result;
    }

    public function fromPrice(Event $event): ?int
    {
        return $this->pricing->lowestPrice($event);
    }
}
