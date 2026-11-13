<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Offres de Pass (RG-01, RG-09).
 */
enum Offer: string
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

    public function tagline(): string
    {
        return match ($this) {
            self::Standard => 'Trajet direct, sans correspondance',
            self::Premium => 'Plus de confort à bord',
            self::Gold => 'Haut de gamme, places réservées',
        };
    }
}
