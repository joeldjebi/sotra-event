<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Type de panne signalée par le convoyeur (CV-17).
 */
enum BreakdownType: string
{
    use HasOptions;

    case Engine = 'moteur';
    case Tyre = 'pneu';
    case Brakes = 'freins';
    case Electrical = 'electrique';
    case Accident = 'accident';
    case Other = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Engine => 'Moteur / surchauffe',
            self::Tyre => 'Pneu crevé',
            self::Brakes => 'Freins',
            self::Electrical => 'Électrique',
            self::Accident => 'Accident',
            self::Other => 'Autre',
        };
    }
}
