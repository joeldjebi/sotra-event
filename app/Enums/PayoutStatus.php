<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Cycle d'un reversement (PA-05) : calculé automatiquement, validé dans la console, puis payé avec justificatif.
 */
enum PayoutStatus: string
{
    use HasOptions;

    case Calculated = 'calcule';
    case Validated = 'valide';
    case Paid = 'paye';

    public function label(): string
    {
        return match ($this) {
            self::Calculated => 'Calculé',
            self::Validated => 'Validé',
            self::Paid => 'Payé',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Calculated => 'neutral',
            self::Validated => 'warning',
            self::Paid => 'success',
        };
    }
}
