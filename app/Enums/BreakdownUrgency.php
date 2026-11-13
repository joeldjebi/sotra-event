<?php

namespace App\Enums;

/**
 * Urgence d'une alerte (ST-02) : urgente si des passagers sont à bord ou en cas d'accident.
 */
enum BreakdownUrgency: string
{
    case Urgent = 'urgent';
    case Normal = 'normal';

    public function label(): string
    {
        return match ($this) {
            self::Urgent => 'Urgent · passagers à bord',
            self::Normal => 'Normal · bus à vide',
        };
    }
}
