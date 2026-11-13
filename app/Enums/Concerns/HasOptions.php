<?php

namespace App\Enums\Concerns;

/**
 * Liste valeur => libellé pour les listes déroulantes.
 */
trait HasOptions
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
