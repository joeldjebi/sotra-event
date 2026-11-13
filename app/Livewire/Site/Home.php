<?php

namespace App\Livewire\Site;

use App\Enums\SalesChannel;
use App\Models\Customer;
use App\Services\Sales\EventCatalog;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Accueil « Événements » (SP-01 à SP-03) : salutation, recherche, événement mis en avant, liste « Bientôt ».
 */
#[Layout('components.layouts.site', ['width' => 'max-w-3xl'])]
#[Title('Événements')]
class Home extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function render(EventCatalog $catalog): View
    {
        $events = $catalog->listing(SalesChannel::Web, $this->search);
        $customer = auth('customer')->user();

        return view('livewire.site.home', [
            'featured' => $this->search === '' ? $events->firstWhere('featured', true) : null,
            'events' => $events,
            'firstName' => $customer instanceof Customer ? $customer->first_name : null,
        ]);
    }
}
