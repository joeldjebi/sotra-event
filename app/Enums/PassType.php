<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Types de Pass (RG-01) : un ou deux segments.
 */
enum PassType: string
{
    use HasOptions;

    case RoundTrip = 'aller_retour';
    case Outbound = 'aller_simple';
    case Return = 'retour_simple';

    public function label(): string
    {
        return match ($this) {
            self::RoundTrip => 'Aller-retour',
            self::Outbound => 'Aller simple',
            self::Return => 'Retour simple',
        };
    }
}
