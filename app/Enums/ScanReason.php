<?php

namespace App\Enums;

/**
 * Motif d'un scan orange ou rouge (CV-08), avec le libellé affiché par l'app convoyeur.
 */
enum ScanReason: string
{
    case OtherBus = 'autre_bus';
    case OtherPoint = 'autre_point';
    case AlreadyUsed = 'deja_utilise';
    case OtherEvent = 'autre_evenement';
    case OfferMismatch = 'offre_differente';
    case SegmentNotPurchased = 'segment_non_achete';
    case Cancelled = 'annule';
    case NotPaid = 'non_paye';
    case Forged = 'falsifie';
    case Outdated = 'qr_perime';
    case UnknownCode = 'code_inconnu';
    case BusFull = 'bus_plein';
    case NotTransferred = 'non_transfere';

    public function label(): string
    {
        return match ($this) {
            self::OtherBus => 'Valide · prévu dans un autre bus',
            self::OtherPoint => 'Valide · autre point de montée',
            self::AlreadyUsed => 'Déjà utilisé',
            self::OtherEvent => 'Autre événement',
            self::OfferMismatch => 'Offre différente de celle du bus',
            self::SegmentNotPurchased => 'Trajet non acheté',
            self::Cancelled => 'Pass annulé',
            self::NotPaid => 'Pass non payé',
            self::Forged => 'QR falsifié',
            self::Outdated => 'Ancien QR (Pass offert à quelqu\'un d\'autre)',
            self::UnknownCode => 'Code inconnu',
            self::BusFull => 'Bus plein',
            self::NotTransferred => 'Passager absent du bus en panne',
        };
    }
}
