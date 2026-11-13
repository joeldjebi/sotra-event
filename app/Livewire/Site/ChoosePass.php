<?php

namespace App\Livewire\Site;

use App\Actions\Orders\CreateOrder;
use App\Enums\Offer;
use App\Enums\PassType;
use App\Enums\SalesChannel;
use App\Exceptions\ApiException;
use App\Models\BoardingPoint;
use App\Models\Customer;
use App\Models\Event;
use App\Rules\ValidPhoneNumber;
use App\Services\Sales\EventCatalog;
use App\Services\Sales\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * « Choisir mon Pass » (SP-04 à SP-08) : point de départ, type de Pass, offre, nombre, total frais inclus.
 * La sélection est conservée dans l'URL : après connexion, le spectateur la retrouve intacte.
 */
#[Layout('components.layouts.site')]
class ChoosePass extends Component
{
    public Event $event;

    #[Url(as: 'point')]
    public ?int $pointId = null;

    #[Url(as: 'type')]
    public string $passType = 'aller_retour';

    #[Url(as: 'offre')]
    public string $offer = 'standard';

    #[Url(as: 'nb')]
    public int $quantity = 1;

    public bool $gift = false;

    public string $beneficiaryPhone = '';

    public function mount(string $slug, EventCatalog $catalog): void
    {
        $this->event = $catalog->onSale(SalesChannel::Web)->with(['venue', 'boardingPoints', 'offers', 'prices', 'dropZones'])->where('slug', $slug)->firstOrFail();
        $this->pointId ??= $this->event->boardingPoints->first()?->id;
        $this->quantity = max(1, min($this->quantity, $this->max()));
    }

    public function increment(): void
    {
        $this->quantity = min($this->quantity + 1, $this->max());
    }

    public function decrement(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
    }

    public function pay(CreateOrder $createOrder): Redirector|RedirectResponse|null
    {
        $customer = auth('customer')->user();

        if (! $customer instanceof Customer) {
            session()->put('url.intended', route('site.events.show', ['slug' => $this->event->slug, 'point' => $this->pointId, 'type' => $this->passType, 'offre' => $this->offer, 'nb' => $this->quantity]));

            return $this->redirectRoute('site.login');
        }

        if ($this->gift) {
            $this->validate(['beneficiaryPhone' => ['required', 'string', new ValidPhoneNumber]], attributes: ['beneficiaryPhone' => 'numéro du bénéficiaire']);
        }

        try {
            $order = $createOrder->handle(
                $customer,
                $this->event,
                (int) $this->pointId,
                Offer::from($this->offer),
                PassType::from($this->passType),
                $this->quantity,
                SalesChannel::Web,
                $this->gift ? $this->beneficiaryPhone : null,
            );
        } catch (ApiException $exception) {
            $this->addError('order', $exception->getMessage());

            return null;
        }

        return $this->redirectRoute('site.orders.pay', $order);
    }

    private function max(): int
    {
        return (int) config('eventtrans.sales.max_passes_per_order');
    }

    public function render(EventCatalog $catalog, PricingService $pricing): View
    {
        $point = $this->event->boardingPoints->firstWhere('id', $this->pointId) ?? $this->event->boardingPoints->first();
        $offers = $catalog->offers($this->event);

        // Types de Pass vendus depuis la zone de ce point (un prix existe pour au moins une offre).
        $availableTypes = array_values(array_filter(PassType::cases(), fn (PassType $type) => collect($offers)
            ->contains(fn (array $offer) => isset($offer['prices'][$type->value][$point?->zone]))));

        if (! in_array($this->passType, array_map(fn (PassType $type) => $type->value, $availableTypes), true)) {
            $this->passType = PassType::RoundTrip->value;
        }

        $quote = null;

        if ($point instanceof BoardingPoint) {
            try {
                $quote = $pricing->quote($this->event, $point, Offer::from($this->offer), PassType::from($this->passType), $this->quantity);
            } catch (ApiException) {
                $quote = null;
            }
        }

        return view('livewire.site.choose-pass', [
            'point' => $point,
            'offers' => $offers,
            'availableTypes' => $availableTypes,
            'quote' => $quote,
            'pointOptions' => $this->event->boardingPoints->mapWithKeys(fn (BoardingPoint $boardingPoint) => [
                $boardingPoint->id => $boardingPoint->commune.' · '.$boardingPoint->place.' · '.$boardingPoint->boardingCallAt()->format('H\hi'),
            ])->all(),
        ])->title('Choisir mon Pass · '.$this->event->name);
    }
}
