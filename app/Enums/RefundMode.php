<?php

namespace App\Enums;

/**
 * Mode de remboursement (PA-04) : par l'API de l'agrégateur quand l'opérateur le permet, sinon manuel.
 */
enum RefundMode: string
{
    case Api = 'api';
    case Manual = 'manuel';

    public function label(): string
    {
        return match ($this) {
            self::Api => 'Automatique (API)',
            self::Manual => 'Manuel',
        };
    }
}
