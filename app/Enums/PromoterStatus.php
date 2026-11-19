<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Statut d'un promoteur : une inscription en ligne attend la validation d'ACCES EVENT ;
 * un promoteur créé dans la console est validé d'office.
 */
enum PromoterStatus: string
{
    use HasOptions;

    case Pending = 'en_attente';
    case Approved = 'valide';
    case Rejected = 'refuse';
    case Suspended = 'suspendu';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de validation',
            self::Approved => 'Validé',
            self::Rejected => 'Refusé',
            self::Suspended => 'Suspendu',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Suspended => 'neutral',
        };
    }
}
