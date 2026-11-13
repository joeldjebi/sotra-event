<?php

namespace App\Livewire\Admin\Events;

use App\Enums\EventStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Event;
use App\Models\User;
use App\Services\EventReadiness;
use App\Services\EventWorkflow;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panneau latéral de la configuration d'un événement : statut, liste de contrôle,
 * proposition à la compagnie, ouverture de la vente (AD-05) et fil de négociation.
 */
class StatusPanel extends Component
{
    use AuthorizesPermission;

    public Event $event;

    /** Étape affichée, pour recharger la même page après une transition. */
    public int $step = 1;

    public string $proposalMessage = '';

    public string $reply = '';

    protected function permission(): Permission
    {
        return Permission::EventsManage;
    }

    #[On('event-updated')]
    public function refreshEvent(): void
    {
        $this->event->refresh();
        $this->event->unsetRelations();
    }

    public function propose(EventWorkflow $workflow): void
    {
        $this->validate(['proposalMessage' => ['nullable', 'string', 'max:2000']]);
        $workflow->propose($this->event, $this->actor(), $this->proposalMessage);
        $this->proposalMessage = '';
        session()->flash('flash', 'Événement proposé à '.$this->event->company->name.'.');
        $this->redirectRoute('admin.events.edit', ['event' => $this->event, 'etape' => $this->step]);
    }

    public function openSale(EventWorkflow $workflow): void
    {
        $workflow->openSale($this->event, $this->actor());
        session()->flash('flash', 'La vente est ouverte.');
        $this->redirectRoute('admin.events.edit', ['event' => $this->event]);
    }

    public function sendReply(EventWorkflow $workflow): void
    {
        $this->validate(['reply' => ['required', 'string', 'max:2000']], attributes: ['reply' => 'message']);
        $workflow->reply($this->event, $this->actor(), $this->reply);
        $this->reply = '';
        $this->notify('Message envoyé à la compagnie.');
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(EventReadiness $readiness): View
    {
        return view('livewire.admin.events.status-panel', [
            'checks' => $readiness->checks($this->event),
            'canPropose' => $readiness->canPropose($this->event),
            'canOpenSale' => $readiness->canOpenSale($this->event),
            'messages' => $this->event->negotiations()->with('user')->get(),
            'canReply' => in_array($this->event->status, [EventStatus::Proposed, EventStatus::Negotiating, EventStatus::Accepted], true),
        ]);
    }
}
