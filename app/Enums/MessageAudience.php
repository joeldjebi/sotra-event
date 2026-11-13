<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Destinataires d'un message du promoteur (PR-04) : tous les détenteurs de Pass, ou par point, bus ou offre.
 */
enum MessageAudience: string
{
    use HasOptions;

    case All = 'tous';
    case Point = 'point';
    case Bus = 'bus';
    case Offer = 'offre';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Tous les détenteurs de Pass',
            self::Point => 'Un point d\'embarquement',
            self::Bus => 'Un bus',
            self::Offer => 'Une offre',
        };
    }
}
