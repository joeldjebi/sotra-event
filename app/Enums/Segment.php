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

    public static function fromDirection(Direction $direction): self
    {
        return $direction === Direction::Outbound ? self::Outbound : self::Return;
    }
}
