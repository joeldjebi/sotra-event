<?php

namespace App\Livewire\Site;

use App\Enums\DropZoneUsage;
use App\Enums\PassStatus;
use App\Models\Customer;
use App\Models\Pass;
use App\Services\Operations\PassTrip;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Mon trajet » (SP-18) : chronologie du Pass choisi, bus suivi en direct et arrivée estimée.
 */
#[Layout('components.layouts.site')]
#[Title('Mon trajet')]
class Trip extends Component
{
    #[Url(as: 'pass')]
    public ?string $code = null;

    public function render(PassTrip $trip): View
    {
        $customer = auth('customer')->user();
        $passes = collect();
        $pass = null;

        if ($customer instanceof Customer) {
            $passes = Pass::query()
                ->with(['event.venue', 'event.dropZones', 'boardingPoint'])
                ->where('holder_id', $customer->id)
                ->whereIn('status', PassStatus::sold())
                ->whereHas('event', fn ($query) => $query->withoutGlobalScopes()->where('ends_at', '>=', now()->subHours(6)))
                ->orderBy('id')
                ->get();

            $pass = $passes->firstWhere('code', $this->code) ?? $passes->first();
        }

        $data = $pass ? $trip->forPass($pass) : null;
        $live = $data['live'] ?? null;
        $dropOff = $pass?->event->dropZones->firstWhere('usage', DropZoneUsage::DropOff);

        return view('livewire.site.trip', [
            'customer' => $customer,
            'passes' => $passes,
            'pass' => $pass,
            'trip' => $data,
            'map' => $pass === null ? null : [
                'center' => [$pass->boardingPoint->location->getLatitude(), $pass->boardingPoint->location->getLongitude()],
                'zoom' => 12,
                'fit' => true,
                'markers' => array_values(array_filter([
                    $live && $live['latitude'] !== null ? ['id' => 'bus', 'lat' => $live['latitude'], 'lng' => $live['longitude'], 'label' => 'Bus '.($live['bus'] ?? ''), 'tone' => 'warning'] : null,
                    ['id' => 'point', 'lat' => $pass->boardingPoint->location->getLatitude(), 'lng' => $pass->boardingPoint->location->getLongitude(), 'label' => $pass->boardingPoint->place, 'tone' => 'brand'],
                ])),
                'zones' => $dropOff ? [['lat' => $dropOff->location->getLatitude(), 'lng' => $dropOff->location->getLongitude(), 'radius' => $dropOff->radius_m, 'label' => $dropOff->name]] : [],
            ],
        ]);
    }
}
