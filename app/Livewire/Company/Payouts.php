<?php

namespace App\Livewire\Company;

use App\Enums\EventStatus;
use App\Enums\PassStatus;
use App\Enums\PayoutStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Event;
use App\Models\Pass;
use App\Models\Payout;
use App\Services\Finance\PayoutCalculator;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Direction commerciale · Reversements (CO-05) : prochain reversement (montant, part,
 * échéance à 5 jours ouvrés), estimation des événements en cours de vente, historique et justificatifs.
 */
class Payouts extends Component
{
    use AuthorizesPermission;

    public ?int $viewingId = null;

    public bool $showDetails = false;

    protected function permission(): Permission
    {
        return Permission::CompanyPayoutsView;
    }

    public function show(int $payoutId): void
    {
        $this->viewingId = Payout::query()->findOrFail($payoutId)->id;
        $this->showDetails = true;
    }

    public function render(PayoutCalculator $calculator): View
    {
        $upcoming = Event::query()->where('status', EventStatus::OnSale)->orderBy('ends_at')->get()
            ->map(fn (Event $event) => [
                'event' => $event,
                'net' => (int) Pass::query()->where('event_id', $event->id)->whereIn('status', PassStatus::sold())->sum('company_amount'),
                'due_on' => PayoutCalculator::dueOn($event->ends_at),
            ]);

        return view('livewire.company.payouts', [
            'next' => Payout::query()->with('event')->where('status', '!=', PayoutStatus::Paid)->orderBy('due_on')->first(),
            'upcoming' => $upcoming,
            'payouts' => Payout::query()->with('event')->latest('due_on')->get(),
            'viewing' => $this->viewingId ? Payout::query()->with('event')->find($this->viewingId) : null,
        ]);
    }
}
