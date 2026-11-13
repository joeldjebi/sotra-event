<?php

namespace App\Enums;

/**
 * Étapes d'une intervention (ST-09), dans l'ordre.
 */
enum InterventionStep: string
{
    case Taken = 'prise_en_charge';
    case EnRoute = 'en_route';
    case OnSite = 'sur_place';
    case Diagnosis = 'diagnostic';
    case Closed = 'cloturee';

    public function label(): string
    {
        return match ($this) {
            self::Taken => 'Prise en charge',
            self::EnRoute => 'En route',
            self::OnSite => 'Sur place',
            self::Diagnosis => 'Diagnostic',
            self::Closed => 'Clôturée',
        };
    }

    public function order(): int
    {
        return array_search($this, self::cases(), true);
    }

    /**
     * Colonne horodatant le passage à cette étape.
     */
    public function timestampColumn(): string
    {
        return match ($this) {
            self::Taken => 'taken_at',
            self::EnRoute => 'en_route_at',
            self::OnSite => 'on_site_at',
            self::Diagnosis => 'diagnosis_at',
            self::Closed => 'closed_at',
        };
    }
}
