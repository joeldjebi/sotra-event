<?php

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Numéro de téléphone normalisé au format E.164 (ex. +2250707080839).
 *
 * Un numéro ivoirien à 10 chiffres saisi sans indicatif reçoit +225.
 * Les numéros étrangers (diaspora) doivent être saisis avec leur indicatif (+33…, 00 33…).
 */
final class PhoneNumber implements Stringable
{
    private function __construct(public readonly string $e164) {}

    public static function parse(string $input): self
    {
        $value = preg_replace('/[\s.\-()]/', '', trim($input)) ?? '';

        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        }

        if (! str_starts_with($value, '+')) {
            $countryCode = (string) config('eventtrans.default_country_code', '225');

            if (preg_match('/^\d{10}$/', $value) === 1) {
                $value = '+'.$countryCode.$value;
            } elseif (str_starts_with($value, $countryCode) && preg_match('/^\d{13}$/', $value) === 1) {
                $value = '+'.$value;
            }
        }

        if (preg_match('/^\+[1-9]\d{7,14}$/', $value) !== 1) {
            throw new InvalidArgumentException("Numéro de téléphone invalide : {$input}");
        }

        return new self($value);
    }

    public static function isValid(string $input): bool
    {
        try {
            self::parse($input);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function equals(self $other): bool
    {
        return $this->e164 === $other->e164;
    }

    public function __toString(): string
    {
        return $this->e164;
    }
}
