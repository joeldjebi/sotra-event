<?php

namespace App\Http\Controllers\Web\Promoter;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Sales\QrCode;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Espace promoteur · Kit de promotion (PR-06) : lien de vente du Pass et QR code à intégrer à la communication.
 * Les événements sont cloisonnés au promoteur connecté (PromoterScope).
 */
class PromoKitController extends Controller
{
    public function index(): View
    {
        return view('promoter.kit', [
            'events' => Event::query()
                ->with('venue')
                ->whereIn('status', [EventStatus::Accepted, EventStatus::OnSale])
                ->where('ends_at', '>', now())
                ->orderBy('starts_at')
                ->get(),
        ]);
    }

    public function qr(Event $event): Response
    {
        abort_unless($event->status === EventStatus::OnSale, 404);

        return response(QrCode::svg(route('site.events.show', $event->slug), 600), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-'.$event->slug.'.svg"',
        ]);
    }
}
