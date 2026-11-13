<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Étape d'une mission (CV-02) ; « incident » pendant une panne (étape 7).
 */
enum MissionStatus: string
{
    use HasOptions;

    case Planned = 'planifiee';
    case Boarding = 'embarquement';
    case OnRoute = 'en_route';
    case Arrived = 'arrivee';
    case Incident = 'incident';
    case Finished = 'terminee';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planifiée',
            self::Boarding => 'Embarquement',
            self::OnRoute => 'En route',
            self::Arrived => 'Arrivée',
            self::Incident => 'Panne',
            self::Finished => 'Terminée',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Planned, self::Finished => 'neutral',
            self::Boarding => 'warning',
            self::OnRoute, self::Arrived => 'success',
            self::Incident => 'danger',
        };
    }
}
