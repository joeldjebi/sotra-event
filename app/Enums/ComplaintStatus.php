<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Statut d'une réclamation (QU-03) : Ouverte, En cours, Traitée.
 */
enum ComplaintStatus: string
{
    use HasOptions;

    case Open = 'ouverte';
    case InProgress = 'en_cours';
    case Resolved = 'traitee';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouverte',
            self::InProgress => 'En cours',
            self::Resolved => 'Traitée',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::InProgress => 'warning',
            self::Resolved => 'success',
        };
    }
}
