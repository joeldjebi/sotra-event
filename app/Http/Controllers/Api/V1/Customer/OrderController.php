<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\Orders\CreateOrder;
use App\Actions\Payments\InitiatePayment;
use App\Enums\Offer;
use App\Enums\PassType;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOrderRequest;
use App\Http\Requests\Api\V1\PayOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\BoardingPoint;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Sales\EventCatalog;
use App\Services\Sales\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Commande et paiement d'un Pass (SP-06 à SP-13).
 *
 * @tags Commandes
 */
class OrderController extends Controller
{
    public function __construct(private readonly EventCatalog $catalog) {}

    /**
     * Calculer le total d'une commande (frais de paiement inclus), sans bloquer de place.
     */
    public function quote(CreateOrderRequest $request, PricingService $pricing): JsonResponse
    {
        $event = $this->catalog->onSale(SalesChannel::App)->findOrFail($request->integer('event_id'));
        $point = BoardingPoint::query()->where('event_id', $event->id)->findOrFail($request->integer('boarding_point_id'));

        $quote = $pricing->quote($event, $point, Offer::from($request->string('offer')->toString()), PassType::from($request->string('pass_type')->toString()), $request->integer('quantity'));

        return response()->json(['data' => $quote->toArray()]);
    }

    /**
     * Créer une commande : les places sont bloquées 15 minutes, le temps du paiement.
     */
    public function store(CreateOrderRequest $request, CreateOrder $createOrder): JsonResponse
    {
        $event = $this->catalog->onSale(SalesChannel::App)->findOrFail($request->integer('event_id'));

        $order = $createOrder->handle(
            $this->customer($request),
            $event,
            $request->integer('boarding_point_id'),
            Offer::from($request->string('offer')->toString()),
            PassType::from($request->string('pass_type')->toString()),
            $request->integer('quantity'),
            SalesChannel::App,
            $request->filled('beneficiary_phone') ? $request->string('beneficiary_phone')->toString() : null,
        );

        return (new OrderResource($order->load(['event', 'boardingPoint'])))->response()->setStatusCode(201);
    }

    /**
     * Suivre une commande (statut du paiement, Pass émis).
     */
    public function show(Request $request, Order $order): OrderResource
    {
        $this->ensureOwner($request, $order);

        return new OrderResource($order->load(['event', 'boardingPoint', 'passes']));
    }

    /**
     * Payer une commande (Mobile Money ou carte). La confirmation arrive par l'agrégateur.
     */
    public function pay(PayOrderRequest $request, Order $order, InitiatePayment $initiatePayment): JsonResponse
    {
        $this->ensureOwner($request, $order);

        $result = $initiatePayment->handle(
            $order,
            PaymentMethod::from($request->string('method')->toString()),
            $request->filled('msisdn') ? $request->string('msisdn')->toString() : null,
        );

        return response()->json(['data' => [
            'reference' => $result['payment']->provider_reference,
            'status' => $result['payment']->status->value,
            'amount' => $result['payment']->amount,
            'instructions' => $result['instructions']->toArray(),
        ]], 202);
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user('sanctum');
        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }

    private function ensureOwner(Request $request, Order $order): void
    {
        abort_unless($order->customer_id === $this->customer($request)->id, 404);
    }
}
