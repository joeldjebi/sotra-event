<?php

namespace App\Enums;

/**
 * Résultat d'un scan (CV-08) : vert = valide ; orange = valide mais autre bus ou autre point ; rouge = refusé.
 */
enum ScanResult: string
{
    case Green = 'vert';
    case Orange = 'orange';
    case Red = 'rouge';

    public function accepted(): bool
    {
        return $this !== self::Red;
    }
}
