<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Cycle de vie d'un événement (§ 3 CDC, P1) :
 * brouillon → proposé → (en négociation ⇄ proposé) → accepté → en vente → terminé ; annulé / reporté.
 */
enum EventStatus: string
{
    use HasOptions;

    case Draft = 'brouillon';
    case Proposed = 'propose';
    case Negotiating = 'en_negociation';
    case Accepted = 'accepte';
    case OnSale = 'en_vente';
    case Finished = 'termine';
    case Cancelled = 'annule';
    case Postponed = 'reporte';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Proposed => 'Proposé à la compagnie',
            self::Negotiating => 'En négociation',
            self::Accepted => 'Accepté',
            self::OnSale => 'En vente',
            self::Finished => 'Terminé',
            self::Cancelled => 'Annulé',
            self::Postponed => 'Reporté',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Proposed, self::Negotiating, self::Postponed => 'warning',
            self::Accepted, self::OnSale => 'success',
            self::Finished => 'neutral',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Points d'embarquement, grille, taux et compagnie modifiables par ACCES EVENT
     * uniquement avant la proposition ou pendant une négociation.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Negotiating], true);
    }

    /**
     * Statuts visibles par la compagnie (un brouillon ne lui est pas montré).
     *
     * @return list<self>
     */
    public static function visibleToCompany(): array
    {
        return [self::Proposed, self::Negotiating, self::Accepted, self::OnSale, self::Finished, self::Cancelled, self::Postponed];
    }
}
