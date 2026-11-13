<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Canaux de vente (AD-04). Les points de vente physiques sont désactivés en V1.
 */
enum SalesChannel: string
{
    use HasOptions;

    case Web = 'web';
    case App = 'app';
    case WhatsApp = 'whatsapp';
    case PointOfSale = 'pdv';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::App => 'Application mobile',
            self::WhatsApp => 'WhatsApp',
            self::PointOfSale => 'Points de vente physiques',
        };
    }

    public function isAvailable(): bool
    {
        return $this !== self::PointOfSale;
    }
}
