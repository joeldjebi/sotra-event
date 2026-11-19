<?php

namespace App\Http\Controllers\Web\Promoter;

use App\Exports\PromoterEventExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Reporting\PromoterEventInsights;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Export Excel d'un événement du promoteur connecté (le scope promoteur cloisonne la recherche).
 */
class EventExportController extends Controller
{
    public function __invoke(int $eventId): BinaryFileResponse
    {
        $event = Event::query()->whereIn('status', PromoterEventInsights::visibleStatuses())->findOrFail($eventId);

        return Excel::download(new PromoterEventExport($event), 'evenement-'.Str::slug($event->name).'.xlsx');
    }
}
