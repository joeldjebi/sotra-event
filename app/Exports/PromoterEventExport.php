<?php

namespace App\Exports;

use App\Models\Event;
use App\Services\Reporting\PromoterEventInsights;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Export Excel d'un événement pour son promoteur : ventes, points d'embarquement, départs, incidents,
 * avis et réclamations. Volumes seulement : ni montant, ni identité des spectateurs.
 */
class PromoterEventExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly Event $event) {}

    /**
     * @return list<ArraySheet>
     */
    public function sheets(): array
    {
        $insights = app(PromoterEventInsights::class);
        $event = $this->event->loadMissing(['offers', 'boardingPoints']);
        $offers = $event->activeOffers();

        $sales = $insights->sales($event);
        $summary = $insights->summary($event);
        $boarding = $insights->boarding($event);
        $transport = $insights->transport($event);
        $reviews = $insights->reviews($event);
        $complaints = $insights->complaints($event);
        $cancellations = $insights->cancellations($event);
        $time = fn ($at): string => $at?->format('d/m/Y H:i') ?? '';

        return [
            new ArraySheet('Synthèse', ['Indicateur', 'Valeur'], [
                ['Événement', $event->name],
                ['Date', $time($event->starts_at)],
                ['Pass vendus', $summary['sold']],
                ['Places ouvertes', $summary['capacity']],
                ['Remplissage (%)', $summary['fill_rate']],
                ['Bus prévus (aller)', $summary['buses_planned']],
                ['Montés à l\'aller', $boarding['outbound']['boarded']],
                ['Montés au retour', $boarding['return']['boarded']],
                ['Départs à l\'heure (%)', $transport['on_time_rate']],
                ['Note moyenne (/5)', $reviews['rating']],
                ['Réclamations', $complaints['open'] + $complaints['in_progress'] + $complaints['resolved']],
                ['Pass remboursés', $cancellations['refunded']],
                ...array_map(fn (array $row): array => ['Pass '.$row['offer']->label(), $row['passes']], $sales['by_offer']),
                ...array_map(fn (array $row): array => ['Pass '.mb_strtolower($row['type']->label()), $row['passes']], $sales['by_type']),
                ...array_map(fn (array $row): array => ['Canal '.$row['label'], $row['passes']], $sales['by_channel']),
            ]),
            new ArraySheet('Ventes par jour', ['Jour', 'Pass vendus'], array_map(
                fn (string $label, int $value): array => [$label, $value],
                $sales['daily']['labels'],
                $sales['daily']['series'][0]['values'],
            )),
            new ArraySheet(
                'Points d\'embarquement',
                ['Commune', 'Lieu', 'Zone', 'Départ aller', 'Départ retour', ...array_map(fn ($offer) => 'Pass '.$offer->label(), $offers), 'Pass vendus', 'Bus prévus', 'Montés aller', 'Montés retour'],
                array_map(fn (array $row): array => [
                    $row['point']->commune, $row['point']->place, $row['point']->zone,
                    $row['point']->departure_at->format('H:i'), $row['point']->return_departure_at->format('H:i'),
                    ...array_map(fn ($offer) => $row['by_offer'][$offer->value] ?? 0, $offers),
                    $row['passes'], $row['buses'], $row['boarded_outbound'], $row['boarded_return'],
                ], $insights->points($event)),
            ),
            new ArraySheet(
                'Départs',
                ['Sens', 'Point', 'Offre', 'Bus', 'Passagers', 'Places', 'Départ prévu', 'Départ réel', 'Écart', 'Arrivée', 'Statut'],
                array_map(fn (array $row): array => [
                    $row['direction']->label(), $row['point'], $row['offer']->label(), $row['bus'] ?? '', $row['boarded'], $row['capacity'],
                    $time($row['planned_at']), $time($row['actual_at']), $row['gap_label'], $time($row['arrived_at']),
                    $row['replaced'] ? 'Remplacé après panne' : $row['status']->label(),
                ], $transport['rows']),
            ),
            new ArraySheet(
                'Incidents',
                ['Signalée', 'Bus', 'Point', 'Lieu', 'Panne', 'Urgente', 'Passagers à bord', 'Issue', 'Durée (min)', 'Statut'],
                array_map(fn (array $row): array => [
                    $time($row['at']), $row['bus'] ?? '', $row['point'] ?? '', $row['place'] ?? '', $row['type'], $row['urgent'] ? 'Oui' : 'Non',
                    $row['passengers'], $row['result'] ?? 'En cours', $row['duration'], $row['status']->label(),
                ], $insights->incidents($event)),
            ),
            new ArraySheet(
                'Avis',
                ['Date', 'Note (/5)', 'Point', 'Commentaire'],
                array_map(fn (array $row): array => [$time($row['at']), $row['rating'], $row['point'] ?? '', $row['comment']], $reviews['comments']),
            ),
            new ArraySheet(
                'Réclamations',
                ['N°', 'Date', 'Motif', 'Objet', 'Point', 'Collective', 'Passagers concernés', 'Statut'],
                array_map(fn (array $row): array => [
                    $row['number'], $time($row['at']), $row['category'], $row['title'], $row['point'] ?? '',
                    $row['collective'] ? 'Oui' : 'Non', $row['affected'], $row['status']->label(),
                ], $complaints['rows']),
            ),
        ];
    }
}
