<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DeviceStatus: string
{
    use HasOptions;

    case Active = 'actif';
    case Blocked = 'bloque';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Blocked => 'Bloqué',
        };
    }
}
