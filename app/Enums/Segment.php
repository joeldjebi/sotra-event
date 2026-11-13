<?php

namespace App\Enums;

/**
 * Segment contrôlé par un scan : aller, retour, ou contrôle (transfert vers un bus de réserve, étape 7).
 */
enum Segment: string
{
    case Outbound = 'aller';
    case Return = 'retour';
    case Control = 'controle';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => 'Aller',
            self::Return => 'Retour',
            self::Control => 'Contrôle',
        };
    }

    public static function fromDirection(Direction $direction): self
    {
        return $direction === Direction::Outbound ? self::Outbound : self::Return;
    }
}
