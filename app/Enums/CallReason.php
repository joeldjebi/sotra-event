<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Motif d'un appel au centre d'appel ACCES TRANS.
 */
enum CallReason: string
{
    use HasOptions;

    case Information = 'information';
    case Purchase = 'achat';
    case PassNotReceived = 'pass_non_recu';
    case Transfer = 'transfert';
    case Cancellation = 'annulation';
    case BusDelay = 'retard_bus';
    case Complaint = 'reclamation';
    case Other = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Information => 'Renseignement',
            self::Purchase => 'Achat ou paiement',
            self::PassNotReceived => 'Pass non reçu',
            self::Transfer => 'Offrir un Pass',
            self::Cancellation => 'Annulation ou remboursement',
            self::BusDelay => 'Bus, retard ou trajet',
            self::Complaint => 'Réclamation',
            self::Other => 'Autre',
        };
    }
}
