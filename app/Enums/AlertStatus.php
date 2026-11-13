<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AlertStatus: string
{
    use HasOptions;

    case Open = 'ouverte';
    case Taken = 'prise_en_charge';
    case Closed = 'cloturee';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouverte',
            self::Taken => 'Prise en charge',
            self::Closed => 'Clôturée',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Taken => 'warning',
            self::Closed => 'success',
        };
    }
}
