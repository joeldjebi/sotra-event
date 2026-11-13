<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Usage d'une zone sur le lieu de l'événement (PR-05).
 */
enum DropZoneUsage: string
{
    use HasOptions;

    case DropOff = 'depose';
    case PickUp = 'reprise';
    case Welcome = 'accueil';

    public function label(): string
    {
        return match ($this) {
            self::DropOff => 'Dépose aller',
            self::PickUp => 'Reprise retour',
            self::Welcome => 'Accueil EVENT TRANS',
        };
    }
}
