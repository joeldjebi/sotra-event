<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Mode de reversement à la compagnie (PA-05).
 */
enum PayoutMethod: string
{
    use HasOptions;

    case BankTransfer = 'virement';
    case MobileMoney = 'mobile_money';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Virement bancaire',
            self::MobileMoney => 'Mobile Money marchand',
        };
    }
}
