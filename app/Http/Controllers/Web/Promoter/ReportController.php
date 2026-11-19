<?php

namespace App\Http\Controllers\Web\Promoter;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Reporting\PromoterEventInsights;
use App\Services\Reporting\PromoterReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Bilan PDF du promoteur (PR-09), enrichi : ventes, points d'embarquement, embarquement, départs, incidents,
 * avis, réclamations et annulations. Volumes seulement. L'événement est cloisonné au promoteur connecté (PromoterScope).
 */
class ReportController extends Controller
{
    public function __invoke(Event $event, PromoterReport $reports, PromoterEventInsights $insights): Response
    {
        abort_unless(in_array($event->status, PromoterEventInsights::visibleStatuses(), true), 404);

        $event->load(['venue', 'company', 'promoter', 'offers', 'boardingPoints']);

        return Pdf::loadView('pdf.promoter-report', [
            'event' => $event,
            'report' => $reports->build($event),
            'summary' => $insights->summary($event),
            'sales' => $insights->sales($event),
            'points' => $insights->points($event),
            'boarding' => $insights->boarding($event),
            'transport' => $insights->transport($event),
            'incidents' => $insights->incidents($event),
            'reviews' => $insights->reviews($event),
            'complaints' => $insights->complaints($event),
            'cancellations' => $insights->cancellations($event),
        ])->download('bilan-'.Str::slug($event->name).'.pdf');
    }
}
