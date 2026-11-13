<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\SalesChannel;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventDetailResource;
use App\Http\Resources\Api\V1\EventSummaryResource;
use App\Services\Sales\EventCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Catalogue des événements en vente sur l'application (SP-01 à SP-05).
 *
 * @tags Événements
 */
class EventController extends Controller
{
    public function __construct(private readonly EventCatalog $catalog) {}

    /**
     * Événements en vente (mis en avant en premier, puis par date).
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return EventSummaryResource::collection($this->catalog->listing(SalesChannel::App, $request->string('q')->toString()));
    }

    /**
     * Détail d'un événement : points d'embarquement, offres, prix par zone et places restantes.
     *
     * @unauthenticated
     */
    public function show(int $event): EventDetailResource
    {
        $model = $this->catalog->onSale(SalesChannel::App)
            ->with(['venue', 'boardingPoints', 'dropZones', 'offers', 'prices'])
            ->findOrFail($event);

        return new EventDetailResource($model);
    }
}
