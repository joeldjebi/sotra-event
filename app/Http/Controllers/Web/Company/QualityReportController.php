<?php

namespace App\Http\Controllers\Web\Company;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Reporting\QualityMetrics;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Rapport qualité PDF d'un événement (QU-05) : indicateurs, notes par critère, équipages, ponctualité, pannes, réclamations.
 * L'événement est cloisonné à la compagnie connectée (scope global).
 */
class QualityReportController extends Controller
{
    public function __invoke(Event $event): Response
    {
        abort_unless(in_array($event->status, [EventStatus::OnSale, EventStatus::Finished], true), 404);
        $metrics = new QualityMetrics($event->load(['venue', 'company']));

        return Pdf::loadView('pdf.quality-report', [
            'event' => $event,
            'summary' => $metrics->summary(),
            'criteria' => $metrics->criteria(),
            'crews' => $metrics->crews(),
            'punctuality' => $metrics->punctuality(),
            'breakdowns' => $metrics->breakdowns(),
            'complaints' => $event->complaints()->with(['mission.bus', 'mission.boardingPoint'])->latest('id')->get(),
        ])->download('qualite-'.Str::slug($event->name).'.pdf');
    }
}
