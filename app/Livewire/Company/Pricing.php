<?php

namespace App\Livewire\Company;

use App\Enums\EventStatus;
use App\Enums\Offer;
use App\Enums\PassType;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\SelectsEvent;
use App\Models\Event;
use App\Models\EventPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Direction commerciale · Offres & grille tarifaire, en consultation : la grille n'est
 * modifiable que dans la console ACCES EVENT ; la compagnie la discute depuis « Événements proposés ».
 */
class Pricing extends Component
{
    use AuthorizesPermission, SelectsEvent;

    protected function permission(): Permission
    {
        return Permission::PricingView;
    }

    /**
     * Tous les événements soumis à la compagnie, sauf les brouillons d'ACCES EVENT.
     *
     * @return Builder<Event>
     */
    protected function selectableEvents(): Builder
    {
        return Event::query()->where('status', '!=', EventStatus::Draft)->orderByDesc('starts_at');
    }

    public function render(): View
    {
        $event = $this->eventId
            ? $this->selectableEvents()->with(['venue', 'offers', 'prices', 'boardingPoints'])->find($this->eventId)
            : null;

        $grid = [];
        $zones = [];

        if ($event !== null) {
            $zones = $event->prices->pluck('zone')->merge($event->boardingPoints->pluck('zone'))->unique()->sort()->values()->all();

            foreach ($event->prices as $price) {
                /** @var EventPrice $price */
                $grid[$price->zone][$price->offer->value][$price->pass_type->value] = $price->price;
            }
        }

        return view('livewire.company.pricing', [
            'event' => $event,
            'eventOptions' => $this->eventOptions(),
            'offers' => $event?->activeOffers() ?? [],
            'passTypes' => PassType::cases(),
            'zones' => $zones,
            'grid' => $grid,
            'allOffers' => Offer::cases(),
        ]);
    }
}
