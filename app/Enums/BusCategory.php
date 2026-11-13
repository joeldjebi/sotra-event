<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Gamme du véhicule. Premium et Gold circulent dans des bus dédiés (RG-09).
 */
enum BusCategory: string
{
    use HasOptions;

    case Standard = 'standard';
    case Premium = 'premium';
    case Gold = 'gold';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Premium => 'Premium',
            self::Gold => 'Gold',
        };
    }
}
