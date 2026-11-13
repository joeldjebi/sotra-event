<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BusStatus: string
{
    use HasOptions;

    case InService = 'en_service';
    case Reserve = 'reserve';
    case Broken = 'en_panne';
    case OutOfService = 'hors_service';

    public function label(): string
    {
        return match ($this) {
            self::InService => 'En service',
            self::Reserve => 'Réserve',
            self::Broken => 'En panne',
            self::OutOfService => 'Hors service',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::InService => 'success',
            self::Reserve => 'neutral',
            self::Broken => 'danger',
            self::OutOfService => 'warning',
        };
    }
}
