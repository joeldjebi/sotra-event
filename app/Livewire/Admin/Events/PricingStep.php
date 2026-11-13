<?php

namespace App\Livewire\Admin\Events;

use App\Enums\Offer;
use App\Enums\PassType;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventPrice;
use App\Services\ZoneResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Étape 3 · Grille tarifaire (AD-03, RG-07) : prix par zone × offre × type de Pass, en FCFA hors frais.
 *
 * - Chaque zone utilisée doit avoir un prix aller-retour pour chaque offre active (AD-06).
 * - Un aller simple ou un retour simple coûte plus de la moitié de l'aller-retour, sans le dépasser.
 * - Un prix simple laissé vide : ce type de Pass n'est pas vendu pour cette zone et cette offre.
 */
class PricingStep extends Component
{
    use AuthorizesPermission;

    public Event $event;

    /** @var list<string> */
    public array $activeOffers = [];

    /** @var array<string, array<int|string, array<string, string>>> prix[type][zone][offre] */
    public array $prices = [];

    protected function permission(): Permission
    {
        return Permission::EventsManage;
    }

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->activeOffers = $event->offers()->get()->map(fn ($offer) => $offer->offer->value)->all();

        $existing = $event->prices()->get();
        $defaults = (array) config('eventtrans.events.default_price_grid');

        foreach (PassType::cases() as $type) {
            foreach (ZoneResolver::zones() as $zone) {
                foreach (Offer::cases() as $offer) {
                    $price = $existing->first(fn (EventPrice $price) => $price->pass_type === $type && $price->zone === $zone && $price->offer === $offer);

                    $this->prices[$type->value][$zone][$offer->value] = $existing->isEmpty()
                        ? (string) ($defaults[$type->value][$zone][$offer->value] ?? '')
                        : ($price === null ? '' : (string) $price->price);
                }
            }
        }

        if ($this->activeOffers === [] && $existing->isEmpty()) {
            $this->activeOffers = [Offer::Standard->value];
        }
    }

    public function save(): void
    {
        abort_unless($this->event->status->isEditable(), 403, 'Événement verrouillé : il a été proposé à la compagnie.');

        $this->validate([
            'activeOffers' => ['required', 'array', 'min:1'],
            'activeOffers.*' => ['in:'.implode(',', array_column(Offer::cases(), 'value'))],
            'prices.*.*.*' => ['nullable', 'integer', 'min:100', 'max:1000000'],
        ], [
            'activeOffers.required' => 'Activez au moins une offre.',
            'prices.*.*.*.integer' => 'Prix invalide (nombre entier de FCFA).',
            'prices.*.*.*.min' => 'Prix trop bas.',
        ]);

        $this->checkGrid();

        DB::transaction(function (): void {
            $before = $this->snapshot();

            $this->event->offers()->whereNotIn('offer', $this->activeOffers)->delete();

            foreach ($this->activeOffers as $offer) {
                $this->event->offers()->firstOrCreate(['offer' => $offer]);
            }

            $this->event->prices()->delete();

            foreach ($this->prices as $type => $zones) {
                foreach ($zones as $zone => $offers) {
                    foreach ($offers as $offer => $price) {
                        if ($price !== '' && in_array($offer, $this->activeOffers, true)) {
                            $this->event->prices()->create(['zone' => (int) $zone, 'offer' => $offer, 'pass_type' => $type, 'price' => (int) $price]);
                        }
                    }
                }
            }

            AuditLog::record('pricing_updated', $this->event, $before, $this->snapshot());
        });

        $this->event->unsetRelation('offers');
        $this->dispatch('event-updated');
        $this->notify('Grille tarifaire enregistrée.');
    }

    /**
     * AD-06 et RG-07, avec un message par cellule en erreur.
     */
    private function checkGrid(): void
    {
        $errors = [];
        $usedZones = $this->event->boardingPoints()->pluck('zone')->unique()->map(fn ($zone) => (int) $zone)->all();

        foreach ($this->activeOffers as $offer) {
            foreach (ZoneResolver::zones() as $zone) {
                $roundTrip = $this->prices[PassType::RoundTrip->value][$zone][$offer] ?? '';

                if ($roundTrip === '' && in_array($zone, $usedZones, true)) {
                    $errors["prices.aller_retour.{$zone}.{$offer}"] = 'Obligatoire : la zone est utilisée par un point.';
                }

                foreach ([PassType::Outbound, PassType::Return] as $simpleType) {
                    $simple = $this->prices[$simpleType->value][$zone][$offer] ?? '';

                    if ($simple === '') {
                        continue;
                    }

                    if ($roundTrip === '') {
                        $errors["prices.{$simpleType->value}.{$zone}.{$offer}"] = 'Renseignez d\'abord l\'aller-retour.';
                    } elseif ((int) $simple * 2 <= (int) $roundTrip || (int) $simple > (int) $roundTrip) {
                        $errors["prices.{$simpleType->value}.{$zone}.{$offer}"] = 'Doit être supérieur à la moitié de l\'aller-retour, sans le dépasser (RG-07).';
                    }
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'offers' => $this->event->offers()->pluck('offer')->sort()->values()->all(),
            'prices' => $this->event->prices()->orderBy('pass_type')->orderBy('zone')->orderBy('offer')->get()
                ->map(fn (EventPrice $price): string => "{$price->pass_type->value}|Z{$price->zone}|{$price->offer->value}={$price->price}")
                ->all(),
        ];
    }

    public function render(): View
    {
        $usedZones = $this->event->boardingPoints()->reorder()->selectRaw('zone, count(*) as total')->groupBy('zone')->pluck('total', 'zone');

        return view('livewire.admin.events.pricing-step', [
            'offers' => Offer::cases(),
            'passTypes' => PassType::cases(),
            'zones' => ZoneResolver::zones(),
            'usedZones' => $usedZones,
            'locked' => ! $this->event->status->isEditable(),
        ]);
    }
}
