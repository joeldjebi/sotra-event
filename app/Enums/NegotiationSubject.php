<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Objet d'un échange de négociation entre la compagnie et ACCES EVENT (CO-04).
 */
enum NegotiationSubject: string
{
    use HasOptions;

    case Pricing = 'tarifs';
    case Volume = 'volume';
    case Schedule = 'horaires';
    case Other = 'autre';
    case Proposal = 'proposition';
    case Acceptance = 'acceptation';

    public function label(): string
    {
        return match ($this) {
            self::Pricing => 'Tarifs',
            self::Volume => 'Volume',
            self::Schedule => 'Horaires',
            self::Other => 'Autre',
            self::Proposal => 'Proposition',
            self::Acceptance => 'Acceptation',
        };
    }

    /**
     * Objets proposés à la compagnie quand elle ouvre une négociation.
     *
     * @return array<string, string>
     */
    public static function negotiable(): array
    {
        return array_intersect_key(self::options(), array_flip([self::Pricing->value, self::Volume->value, self::Schedule->value, self::Other->value]));
    }
}
