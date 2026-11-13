<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TechTeamType: string
{
    use HasOptions;

    case Light = 'leger';
    case Heavy = 'lourd';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Dépannage léger',
            self::Heavy => 'Dépannage lourd / remorquage',
        };
    }
}
