<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\Passes\TransferPass;
use App\Enums\PassStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PassResource;
use App\Models\Customer;
use App\Models\Pass;
use App\Rules\ValidPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Mes Pass, détail avec QR, et « Offrir ce Pass » (SP-14 à SP-20).
 *
 * @tags Pass
 */
class PassController extends Controller
{
    /**
     * Mes Pass : à venir (par défaut) ou passés.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $past = $request->query('status') === 'past';

        $passes = Pass::query()
            ->with(['event.venue', 'event.dropZones', 'boardingPoint'])
            ->where('holder_id', $this->customer($request)->id)
            ->whereIn('status', PassStatus::owned())
            ->whereHas('event', fn ($query) => $query->withoutGlobalScopes()->where('ends_at', $past ? '<' : '>=', now()))
            ->orderBy('id')
            ->get();

        return PassResource::collection($passes);
    }

    /**
     * Détail d'un Pass, avec le jeton QR signé (affichable hors connexion).
     */
    public function show(Request $request, Pass $pass): PassResource
    {
        abort_unless($pass->holder_id === $this->customer($request)->id, 404);

        return new PassResource($pass->load(['event.venue', 'event.dropZones', 'boardingPoint']));
    }

    /**
     * Offrir ce Pass à un autre numéro (tant que l'aller n'est pas scanné). L'ancien QR devient invalide.
     */
    public function transfer(Request $request, Pass $pass, TransferPass $transfer): PassResource
    {
        $request->validate(['phone' => ['required', 'string', new ValidPhoneNumber]]);
        $customer = $this->customer($request);
        abort_unless($pass->holder_id === $customer->id, 404);

        $pass = $transfer->handle($pass, $customer, $request->string('phone')->toString());

        return new PassResource($pass->load(['event.venue', 'event.dropZones', 'boardingPoint']));
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user('sanctum');
        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }
}
