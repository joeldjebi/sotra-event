<?php

namespace App\Support;

/**
 * Affichage des montants (FCFA entiers) et des nombres à la française :
 * « 9 180 000 FCFA », « 9,18 M », « 450 k », « +12,4 % ».
 */
final class Amount
{
    /** Espace fine insécable, séparateur des milliers en français. */
    private const SPACE = "\u{202F}";

    public static function number(int|float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, ',', self::SPACE);
    }

    public static function fcfa(int $value): string
    {
        return self::number($value)."\u{00A0}FCFA";
    }

    /**
     * Forme compacte : 9,18 M · 450 k · 9 800.
     */
    public static function compact(int|float $value): string
    {
        $absolute = abs($value);

        if ($absolute >= 1_000_000) {
            return self::trim(number_format($value / 1_000_000, $absolute >= 100_000_000 ? 0 : 2, ',', self::SPACE))."\u{00A0}M";
        }

        if ($absolute >= 10_000) {
            return self::trim(number_format($value / 1_000, $absolute >= 100_000 ? 0 : 1, ',', self::SPACE))."\u{00A0}k";
        }

        return self::number($value);
    }

    /**
     * Variation signée en pourcentage (ratio 0,124 → « +12,4 % »).
     */
    public static function percentChange(float $ratio): string
    {
        $percent = round($ratio * 100, abs($ratio) >= 0.1 ? 0 : 1);
        $sign = $percent > 0 ? '+' : ($percent < 0 ? '−' : '');

        return $sign.self::trim(number_format(abs($percent), 1, ',', self::SPACE))."\u{00A0}%";
    }

    public static function percent(float $ratio): string
    {
        return self::number((int) round($ratio * 100))."\u{00A0}%";
    }

    private static function trim(string $value): string
    {
        return str_contains($value, ',') ? rtrim(rtrim($value, '0'), ',') : $value;
    }
}
