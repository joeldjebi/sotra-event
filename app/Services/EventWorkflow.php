<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\NegotiationSubject;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventNegotiation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transitions du cycle de vie d'un événement (P1) et fil de négociation (CO-04).
 * Chaque transition est journalisée et vérifiée côté serveur.
 */
class EventWorkflow
{
    public function __construct(private readonly EventReadiness $readiness) {}

    public function propose(Event $event, User $by, ?string $message = null): void
    {
        if (! $this->readiness->canPropose($event)) {
            throw ValidationException::withMessages(['event' => 'Complétez les informations, les points d\'embarquement et la grille avant de proposer l\'événement.']);
        }

        $this->transition($event, EventStatus::Proposed, $by, EventNegotiation::SIDE_ACCES_EVENT, NegotiationSubject::Proposal, $message, ['proposed_at' => now()]);
    }

    public function accept(Event $event, User $by, ?string $message = null): void
    {
        $this->ensureStatus($event, [EventStatus::Proposed]);

        $this->transition($event, EventStatus::Accepted, $by, EventNegotiation::SIDE_COMPANY, NegotiationSubject::Acceptance, $message, ['accepted_at' => now()]);
    }

    public function negotiate(Event $event, User $by, NegotiationSubject $subject, string $message): void
    {
        $this->ensureStatus($event, [EventStatus::Proposed, EventStatus::Negotiating]);

        $this->transition($event, EventStatus::Negotiating, $by, EventNegotiation::SIDE_COMPANY, $subject, $message);
    }

    /**
     * Réponse d'ACCES EVENT dans le fil, sans changer le statut.
     */
    public function reply(Event $event, User $by, string $message): void
    {
        $this->ensureStatus($event, [EventStatus::Proposed, EventStatus::Negotiating, EventStatus::Accepted]);

        $event->negotiations()->create([
            'user_id' => $by->id,
            'side' => EventNegotiation::SIDE_ACCES_EVENT,
            'subject' => NegotiationSubject::Other,
            'message' => $message,
        ]);
    }

    /**
     * Ouverture de la vente (AD-05) : liste de contrôle complète et événement accepté.
     */
    public function openSale(Event $event, User $by): void
    {
        if (! $this->readiness->canOpenSale($event)) {
            throw ValidationException::withMessages(['event' => 'La vente ne peut pas encore être ouverte : consultez la liste de contrôle.']);
        }

        DB::transaction(function () use ($event): void {
            $previous = $event->status;
            $event->update(['status' => EventStatus::OnSale, 'sale_opened_at' => now()]);
            AuditLog::record('sale_opened', $event, ['status' => $previous->value], ['status' => EventStatus::OnSale->value]);
        });
    }

    /**
     * @param  list<EventStatus>  $allowed
     */
    private function ensureStatus(Event $event, array $allowed): void
    {
        if (! in_array($event->status, $allowed, true)) {
            throw ValidationException::withMessages(['event' => "Action impossible : l'événement est « {$event->status->label()} »."]);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transition(Event $event, EventStatus $to, User $by, string $side, NegotiationSubject $subject, ?string $message, array $extra = []): void
    {
        DB::transaction(function () use ($event, $to, $by, $side, $subject, $message, $extra): void {
            $previous = $event->status;
            $event->update(['status' => $to] + $extra);

            $event->negotiations()->create([
                'user_id' => $by->id,
                'side' => $side,
                'subject' => $subject,
                'message' => $message !== null && trim($message) !== '' ? trim($message) : null,
            ]);

            AuditLog::record('status_changed', $event, ['status' => $previous->value], ['status' => $to->value]);
        });
    }
}
