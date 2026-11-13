<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Statuts d'un Pass (§ 11 CDC) et transitions autorisées.
 */
enum PassStatus: string
{
    use HasOptions;

    case PendingPayment = 'en_attente_paiement';
    case Valid = 'valide';
    case OutboundUsed = 'aller_utilise';
    case ReturnUsed = 'retour_utilise';
    case Completed = 'termine';
    case Cancelled = 'annule';
    case Refunded = 'rembourse';
    case Expired = 'expire';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'En attente de paiement',
            self::Valid => 'Valide',
            self::OutboundUsed => 'Aller utilisé',
            self::ReturnUsed => 'Retour utilisé',
            self::Completed => 'Terminé',
            self::Cancelled => 'Annulé',
            self::Refunded => 'Remboursé',
            self::Expired => 'Expiré',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Valid, self::OutboundUsed, self::ReturnUsed => 'success',
            self::PendingPayment => 'warning',
            self::Cancelled, self::Expired => 'danger',
            self::Completed, self::Refunded => 'neutral',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Valid, self::Expired],
            self::Valid => [self::OutboundUsed, self::ReturnUsed, self::Completed, self::Cancelled],
            self::OutboundUsed, self::ReturnUsed => [self::Completed],
            self::Cancelled => [self::Refunded],
            self::Completed, self::Refunded, self::Expired => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Statuts qui occupent une place (le statut « en attente » ne compte que tant que la commande n'a pas expiré).
     *
     * @return list<self>
     */
    public static function holdingSeat(): array
    {
        return [self::PendingPayment, self::Valid, self::OutboundUsed, self::ReturnUsed, self::Completed];
    }

    /**
     * Statuts d'un Pass utilisable ou déjà utilisé, affiché au spectateur.
     *
     * @return list<self>
     */
    public static function owned(): array
    {
        return [self::Valid, self::OutboundUsed, self::ReturnUsed, self::Completed, self::Cancelled, self::Refunded];
    }
}
