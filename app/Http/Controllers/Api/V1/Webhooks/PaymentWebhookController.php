<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Actions\Payments\HandlePaymentNotification;
use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook de l'agrégateur (PA-02) : seule source de confirmation d'un paiement, avec la réconciliation.
 *
 * @tags Paiement
 */
class PaymentWebhookController extends Controller
{
    /**
     * Notification de paiement signée par l'agrégateur.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request, string $provider, PaymentGateway $gateway, HandlePaymentNotification $handler): JsonResponse
    {
        abort_unless($provider === $gateway->name(), 404);

        $handler->handle($provider, $gateway->parseWebhook($request));

        return response()->json(['received' => true]);
    }
}
