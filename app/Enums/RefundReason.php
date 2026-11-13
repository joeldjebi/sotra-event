<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Motif d'un remboursement (§ 11 CDC) : il fixe la retenue et le délai.
 */
enum RefundReason: string
{
    use HasOptions;

    case EventCancelled = 'evenement_annule';
    case DepartureCancelled = 'depart_annule';
    case EventPostponed = 'evenement_reporte';
    case Customer = 'client';
    case Goodwill = 'geste_commercial';

    public function label(): string
    {
        return match ($this) {
            self::EventCancelled => 'Événement annulé',
            self::DepartureCancelled => 'Départ annulé',
            self::EventPostponed => 'Événement reporté',
            self::Customer => 'Annulation par le spectateur',
            self::Goodwill => 'Geste commercial',
        };
    }

    /**
     * Frais d'annulation retenus (RG-19 : 8 % du total payé ; aucun sinon).
     */
    public function retainsFee(): bool
    {
        return $this === self::Customer;
    }

    /**
     * Délai de remboursement : 48 h après un report (RG-18), 72 h sinon (RG-19).
     */
    public function delayHours(): int
    {
        return $this === self::EventPostponed ? 48 : 72;
    }
}
