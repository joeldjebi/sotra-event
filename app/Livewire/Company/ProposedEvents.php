<?php

namespace App\Livewire\Company;

use App\Enums\EventStatus;
use App\Enums\NegotiationSubject;
use App\Enums\Offer;
use App\Enums\PassType;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Event;
use App\Models\User;
use App\Services\EventWorkflow;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Direction commerciale · Événements proposés (CO-04) :
 * consulter la proposition, l'accepter ou ouvrir une négociation sur les tarifs ou le volume.
 */
class ProposedEvents extends Component
{
    use AuthorizesPermission;

    public ?int $viewingId = null;

    public bool $showDetails = false;

    public string $subject = 'tarifs';

    public string $message = '';

    protected function permission(): Permission
    {
        return Permission::ProposedEventsRespond;
    }

    public function show(int $eventId): void
    {
        $this->viewingId = $this->findEvent($eventId)->id;
        $this->reset(['subject', 'message']);
        $this->resetValidation();
        $this->showDetails = true;
    }

    public function accept(int $eventId, EventWorkflow $workflow): void
    {
        $event = $this->findEvent($eventId);
        $workflow->accept($event, $this->actor(), $this->message ?: null);
        $this->message = '';
        $this->notify("{$event->name} accepté.");
    }

    public function negotiate(EventWorkflow $workflow): void
    {
        $this->validate([
            'subject' => ['required', Rule::in(array_keys(NegotiationSubject::negotiable()))],
            'message' => ['required', 'string', 'max:2000'],
        ], attributes: ['subject' => 'objet', 'message' => 'message']);

        $event = $this->findEvent((int) $this->viewingId);
        $workflow->negotiate($event, $this->actor(), NegotiationSubject::from($this->subject), $this->message);
        $this->message = '';
        $this->notify('Votre demande a été transmise à ACCES EVENT.');
    }

    /**
     * Le scope compagnie limite déjà aux événements de la compagnie ; un brouillon reste invisible.
     */
    private function findEvent(int $eventId): Event
    {
        return Event::query()->whereIn('status', EventStatus::visibleToCompany())->findOrFail($eventId);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        $events = Event::query()
            ->with(['venue', 'promoter'])
            ->withCount('boardingPoints')
            ->whereIn('status', EventStatus::visibleToCompany())
            ->orderByRaw("case status when 'propose' then 0 when 'en_negociation' then 1 else 2 end")
            ->orderBy('starts_at')
            ->get();

        $viewing = $this->viewingId
            ? Event::query()->with(['venue', 'promoter', 'boardingPoints', 'prices', 'offers', 'negotiations.user', 'transportLead'])->find($this->viewingId)
            : null;

        return view('livewire.company.proposed-events', [
            'pending' => $events->filter(fn (Event $event) => in_array($event->status, [EventStatus::Proposed, EventStatus::Negotiating], true)),
            'others' => $events->reject(fn (Event $event) => in_array($event->status, [EventStatus::Proposed, EventStatus::Negotiating], true)),
            'viewing' => $viewing,
            'subjects' => NegotiationSubject::negotiable(),
            'roundTrip' => PassType::RoundTrip,
            'offers' => Offer::cases(),
        ]);
    }
}
