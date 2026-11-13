<?php

namespace App\Exports;

use App\Models\Event;
use App\Services\Reporting\EventSalesReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export Excel des ventes d'un événement (CO-06) : une ligne par point d'embarquement, puis le total.
 */
class EventSalesExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private readonly Event $event) {}

    /**
     * @return list<list<string|int>>
     */
    public function array(): array
    {
        $report = app(EventSalesReport::class)->build($this->event);
        $rows = [];

        foreach ($report['by_point'] as $row) {
            $rows[] = array_merge(
                [$row['point']->commune, $row['point']->place, 'Zone '.$row['point']->zone],
                array_values($row['by_offer']),
                [$row['passes'], $row['buses'], $row['revenue']],
            );
        }

        $rows[] = array_merge(
            ['Total', '', ''],
            array_map(fn (array $offer) => $offer['passes'], $report['by_offer']),
            [$report['sold'], $report['buses_planned'], $report['gross']],
        );

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_merge(
            ['Commune', 'Point de montée', 'Zone'],
            array_map(fn ($offer) => 'Pass '.$offer->label(), $this->event->loadMissing('offers')->activeOffers()),
            ['Pass vendus', 'Bus à convoyer', 'Recette (FCFA, hors frais)'],
        );
    }

    public function title(): string
    {
        return 'Ventes';
    }
}
