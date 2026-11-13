<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Résultat d'une intervention (ST-13, choix unique) ; le remorquage peut s'ajouter à un remplacement.
 */
enum InterventionResult: string
{
    use HasOptions;

    case Repaired = 'repare';
    case Replaced = 'remplace';
    case Towing = 'remorquage';

    public function label(): string
    {
        return match ($this) {
            self::Repaired => 'Réparé sur place',
            self::Replaced => 'Bus remplacé',
            self::Towing => 'Remorquage nécessaire',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Repaired => 'Le bus reprend sa mission',
            self::Replaced => 'Passagers transférés dans un bus de réserve',
            self::Towing => 'Demande de dépanneuse au dépôt',
        };
    }
}
