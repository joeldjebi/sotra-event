<?php

namespace App\Http\Controllers\Web\Promoter;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Reporting\PromoterReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Bilan PDF du promoteur (PR-09). L'événement est cloisonné au promoteur connecté (PromoterScope).
 */
class ReportController extends Controller
{
    public function __invoke(Event $event, PromoterReport $reports): Response
    {
        abort_unless(in_array($event->status, [EventStatus::OnSale, EventStatus::Finished], true), 404);

        return Pdf::loadView('pdf.promoter-report', [
            'event' => $event->load(['venue', 'company', 'promoter']),
            'report' => $reports->build($event),
        ])->download('bilan-'.Str::slug($event->name).'.pdf');
    }
}
