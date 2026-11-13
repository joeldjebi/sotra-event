<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Statut d'une commande. « À rembourser » : paiement confirmé après expiration, places reprises entre-temps.
 */
enum OrderStatus: string
{
    use HasOptions;

    case PendingPayment = 'en_attente_paiement';
    case Paid = 'payee';
    case Failed = 'echouee';
    case Expired = 'expiree';
    case RefundRequired = 'a_rembourser';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'En attente de paiement',
            self::Paid => 'Payée',
            self::Failed => 'Paiement échoué',
            self::Expired => 'Expirée',
            self::RefundRequired => 'À rembourser',
        };
    }
}
