<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Moyens de paiement (SP-10, PA-01).
 */
enum PaymentMethod: string
{
    use HasOptions;

    case OrangeMoney = 'orange_money';
    case MtnMomo = 'mtn_momo';
    case MoovMoney = 'moov_money';
    case Wave = 'wave';
    case Card = 'carte';

    public function label(): string
    {
        return match ($this) {
            self::OrangeMoney => 'Orange Money',
            self::MtnMomo => 'MTN MoMo',
            self::MoovMoney => 'Moov Money',
            self::Wave => 'Wave',
            self::Card => 'Carte bancaire',
        };
    }

    public function isMobileMoney(): bool
    {
        return $this !== self::Card;
    }
}
