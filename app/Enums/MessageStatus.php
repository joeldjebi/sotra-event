<?php

namespace App\Enums;

/**
 * Statut d'envoi d'un message du promoteur (PR-08).
 */
enum MessageStatus: string
{
    case Sent = 'envoye';
    case NoRecipient = 'sans_destinataire';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Envoyé',
            self::NoRecipient => 'Aucun destinataire',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Sent => 'success',
            self::NoRecipient => 'neutral',
        };
    }
}
