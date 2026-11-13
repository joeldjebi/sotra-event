<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'en_attente';
    case Succeeded = 'reussi';
    case Failed = 'echoue';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de confirmation',
            self::Succeeded => 'Réussi',
            self::Failed => 'Échoué',
        };
    }
}
