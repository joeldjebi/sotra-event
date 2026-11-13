<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Disponibilité d'une équipe technique (ST-01).
 */
enum TechTeamStatus: string
{
    use HasOptions;

    case Available = 'disponible';
    case OnIntervention = 'en_intervention';
    case Unavailable = 'indisponible';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::OnIntervention => 'En intervention',
            self::Unavailable => 'Indisponible',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::OnIntervention => 'warning',
            self::Unavailable => 'neutral',
        };
    }
}
