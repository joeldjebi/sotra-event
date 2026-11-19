<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Demande d'événement d'un promoteur : soumise → acceptée (l'événement est créé) ou refusée ; retirée par le promoteur.
 */
enum EventRequestStatus: string
{
    use HasOptions;

    case Submitted = 'soumise';
    case Accepted = 'acceptee';
    case Rejected = 'refusee';
    case Withdrawn = 'retiree';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'En cours d\'étude',
            self::Accepted => 'Acceptée',
            self::Rejected => 'Refusée',
            self::Withdrawn => 'Retirée',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'neutral',
        };
    }
}
