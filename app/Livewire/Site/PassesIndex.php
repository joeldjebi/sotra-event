<?php

namespace App\Livewire\Site;

use App\Enums\PassStatus;
use App\Models\Customer;
use App\Models\Pass;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Mes Pass, à venir et passés (SP-20).
 */
#[Layout('components.layouts.site')]
#[Title('Mes Pass')]
class PassesIndex extends Component
{
    #[Url(as: 'periode', except: 'a-venir')]
    public string $period = 'a-venir';

    public function render(): View
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();
        $past = $this->period === 'passes';

        return view('livewire.site.passes-index', [
            'passes' => Pass::query()
                ->with(['event.venue', 'boardingPoint'])
                ->where('holder_id', $customer->id)
                ->whereIn('status', PassStatus::owned())
                ->whereHas('event', fn ($query) => $query->withoutGlobalScopes()->where('ends_at', $past ? '<' : '>=', now()))
                ->orderBy('id')
                ->get(),
        ]);
    }
}
