<?php

namespace App\Livewire\Admin\Events;

use App\Enums\EventStatus;
use App\Enums\PassStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Event;
use App\Models\User;
use App\Services\Finance\EventDisruption;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Console · Annulation / report (AD-13) : annuler l'événement ou un départ (remboursement intégral, RG-17),
 * ou reporter l'événement à une nouvelle date (Pass valables, remboursement sans frais sur demande, RG-18).
 * Chaque action prévient les détenteurs de Pass par notification, SMS et WhatsApp.
 */
class Disruption extends Component
{
    use AuthorizesPermission;

    public Event $event;

    public string $cancelReason = '';

    public string $newDate = '';

    public string $newTime = '';

    public ?int $departureId = null;

    public string $departureReason = '';

    protected function permission(): Permission
    {
        return Permission::EventsManage;
    }

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->newTime = $event->starts_at->format('H:i');
    }

    public function cancelEvent(EventDisruption $disruption): void
    {
        $this->validate(['cancelReason' => ['required', 'string', 'min:5', 'max:200']], attributes: ['cancelReason' => 'motif']);
        $this->run(fn () => $disruption->cancelEvent($this->event, trim($this->cancelReason), $this->user()), 'Événement annulé : :count Pass remboursés intégralement, détenteurs prévenus.');
    }

    public function postpone(EventDisruption $disruption): void
    {
        $this->validate(['newDate' => ['required', 'date_format:Y-m-d'], 'newTime' => ['required', 'date_format:H:i']], attributes: ['newDate' => 'nouvelle date', 'newTime' => 'heure']);
        $start = Carbon::parse("{$this->newDate} {$this->newTime}", config('app.timezone'));
        $this->run(fn () => $disruption->postpone($this->event, $start, $this->user()), 'Événement reporté : :count détenteurs prévenus, leurs Pass restent valables.');
    }

    public function cancelDeparture(EventDisruption $disruption): void
    {
        $this->validate([
            'departureId' => ['required', 'integer'],
            'departureReason' => ['required', 'string', 'min:5', 'max:200'],
        ], attributes: ['departureId' => 'départ', 'departureReason' => 'motif']);
        $point = $this->event->boardingPoints()->findOrFail($this->departureId);
        $this->run(fn () => $disruption->cancelDeparture($point, trim($this->departureReason), $this->user()), "Départ de {$point->commune} annulé : :count Pass remboursés intégralement.");
        $this->reset(['departureId', 'departureReason']);
    }

    /**
     * @param  callable(): int  $action
     */
    private function run(callable $action, string $message): void
    {
        try {
            $count = $action();
        } catch (ValidationException $exception) {
            $this->notify((string) collect($exception->errors())->flatten()->first(), 'danger');

            return;
        }

        $this->event->refresh();
        $this->notify(str_replace(':count', (string) $count, $message));
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        $valid = $this->event->passes()->where('status', PassStatus::Valid);

        return view('livewire.admin.events.disruption', [
            'active' => in_array($this->event->status, [EventStatus::Accepted, EventStatus::OnSale], true),
            'validPasses' => (clone $valid)->count(),
            'paidTotal' => (int) (clone $valid)->sum('price') + (int) (clone $valid)->sum('fee_amount'),
            'points' => $this->event->boardingPoints()->orderBy('position')->withCount(['passes as valid_passes_count' => fn ($query) => $query->where('status', PassStatus::Valid)])->get(),
            'refunds' => $this->event->refunds()->selectRaw('status, count(*) as total, sum(amount) as amount')->groupBy('status')->get(),
        ]);
    }
}
