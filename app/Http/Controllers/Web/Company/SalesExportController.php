<?php

namespace App\Http\Controllers\Web\Company;

use App\Enums\EventStatus;
use App\Exports\EventSalesExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Rapports · ventes d'un événement en Excel (CO-06). L'événement est cloisonné à la compagnie connectée (scope global).
 */
class SalesExportController extends Controller
{
    public function __invoke(Event $event): BinaryFileResponse
    {
        abort_unless(in_array($event->status, [EventStatus::OnSale, EventStatus::Finished, EventStatus::Postponed, EventStatus::Cancelled], true), 404);

        return Excel::download(new EventSalesExport($event), 'ventes-'.Str::slug($event->name).'.xlsx');
    }
}
