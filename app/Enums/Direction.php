<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Sens d'une mission.
 */
enum Direction: string
{
    use HasOptions;

    case Outbound = 'aller';
    case Return = 'retour';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => 'Aller',
            self::Return => 'Retour',
        };
    }
}
