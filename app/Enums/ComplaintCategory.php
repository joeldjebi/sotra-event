<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Motif d'une réclamation (SP-23). « Retard » est aussi le motif des réclamations collectives automatiques.
 */
enum ComplaintCategory: string
{
    use HasOptions;

    case MissedBus = 'bus_parti';
    case Delay = 'retard';
    case Behaviour = 'comportement';
    case Comfort = 'confort';
    case LostItem = 'objet_perdu';
    case Boarding = 'refus_montee';
    case Other = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::MissedBus => 'Bus parti sans moi',
            self::Delay => 'Retard',
            self::Behaviour => 'Comportement de l\'équipage',
            self::Comfort => 'Confort / propreté du bus',
            self::LostItem => 'Objet perdu',
            self::Boarding => 'Montée refusée',
            self::Other => 'Autre',
        };
    }
}
