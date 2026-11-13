<?php

namespace App\Services;

use App\Enums\DropZoneUsage;
use App\Enums\EventStatus;
use App\Enums\PassType;
use App\Models\Event;

/**
 * Liste de contrôle d'un événement : ce qui manque pour le proposer à la compagnie puis ouvrir la vente
 * (AD-05, AD-06).
 */
class EventReadiness
{
    /**
     * @return array<string, array{label: string, ok: bool, problems: list<string>}>
     */
    public function checks(Event $event): array
    {
        $event->loadMissing(['offers', 'boardingPoints', 'prices', 'dropZones']);

        return [
            'informations' => $this->section('Informations', $this->informationProblems($event)),
            'points' => $this->section('Points d\'embarquement', $this->pointProblems($event)),
            'pricing' => $this->section('Grille tarifaire', $this->pricingProblems($event)),
            'publication' => $this->section('Canaux & publication', $this->publicationProblems($event)),
            'acceptance' => $this->section('Acceptation par la compagnie', in_array($event->status, [EventStatus::Accepted, EventStatus::OnSale], true)
                ? [] : ['La compagnie n\'a pas encore accepté l\'événement.']),
            'capacity' => $this->section('Bus et places par offre', $this->capacityProblems($event)),
        ];
    }

    /**
     * Proposition possible : informations, points et grille complets (la compagnie juge sur cette base).
     */
    public function canPropose(Event $event): bool
    {
        if (! in_array($event->status, [EventStatus::Draft, EventStatus::Negotiating], true)) {
            return false;
        }

        $checks = $this->checks($event);

        return $checks['informations']['ok'] && $checks['points']['ok'] && $checks['pricing']['ok'];
    }

    /**
     * Ouverture de la vente (AD-05) : les 4 étapes complètes, événement accepté, bus et places définis.
     */
    public function canOpenSale(Event $event): bool
    {
        if ($event->status !== EventStatus::Accepted) {
            return false;
        }

        foreach ($this->checks($event) as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $problems
     * @return array{label: string, ok: bool, problems: list<string>}
     */
    private function section(string $label, array $problems): array
    {
        return ['label' => $label, 'ok' => $problems === [], 'problems' => $problems];
    }

    /**
     * @return list<string>
     */
    private function informationProblems(Event $event): array
    {
        $problems = [];

        if ($event->transport_lead_id === null) {
            $problems[] = 'Désignez le chef de projet transport du promoteur.';
        }

        if ($event->ends_at->lte($event->starts_at)) {
            $problems[] = 'La fin prévue doit être après le début de l\'événement.';
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function pointProblems(Event $event): array
    {
        if ($event->boardingPoints->isEmpty()) {
            return ['Ajoutez au moins un point d\'embarquement.'];
        }

        $problems = [];

        foreach ($event->boardingPoints as $point) {
            if ($point->return_departure_at->lt($event->ends_at)) {
                $problems[] = "{$point->commune} · {$point->place} : le départ retour doit être après la fin prévue de l'événement (AD-06).";
            }

            if ($point->departure_at->gte($event->starts_at)) {
                $problems[] = "{$point->commune} · {$point->place} : le départ aller doit précéder le début de l'événement.";
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function pricingProblems(Event $event): array
    {
        $offers = $event->activeOffers();

        if ($offers === []) {
            return ['Activez au moins une offre (Standard, Premium ou Gold).'];
        }

        $problems = [];

        foreach ($event->usedZones() as $zone) {
            foreach ($offers as $offer) {
                $hasRoundTrip = $event->prices->contains(fn ($price) => $price->zone === $zone
                    && $price->offer === $offer && $price->pass_type === PassType::RoundTrip);

                if (! $hasRoundTrip) {
                    $problems[] = "Zone {$zone} · {$offer->label()} : prix aller-retour manquant (AD-06).";
                }
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function publicationProblems(Event $event): array
    {
        $problems = [];

        if ($event->channels === []) {
            $problems[] = 'Activez au moins un canal de vente.';
        }

        foreach ([DropZoneUsage::DropOff, DropZoneUsage::PickUp] as $usage) {
            if (! $event->dropZones->contains(fn ($zone) => $zone->usage === $usage)) {
                $problems[] = "Définissez une zone « {$usage->label()} ».";
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function capacityProblems(Event $event): array
    {
        if ($event->offers->isEmpty()) {
            return ['Aucune offre active.'];
        }

        $problems = [];

        foreach ($event->offers as $offer) {
            if ($offer->capacity() === null) {
                $problems[] = "{$offer->offer->label()} : la compagnie doit indiquer le nombre de bus et de places par bus (RG-08).";
            }
        }

        return $problems;
    }
}
