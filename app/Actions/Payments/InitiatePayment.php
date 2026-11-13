<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Data\PaymentInstructions;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\PhoneNumber;
use Illuminate\Support\Str;

/**
 * Lance le paiement d'une commande (SP-09 à SP-13). La commande n'est payée qu'à la confirmation de
 * l'agrégateur (webhook ou réconciliation).
 */
class InitiatePayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * @return array{payment: Payment, instructions: PaymentInstructions}
     */
    public function handle(Order $order, PaymentMethod $method, ?string $msisdn): array
    {
        if (! $order->isPayable()) {
            throw new ApiException('Cette commande ne peut plus être payée. Recommencez votre réservation.', 'ORDER_NOT_PAYABLE');
        }

        if ($method->isMobileMoney()) {
            if ($msisdn === null || ! PhoneNumber::isValid($msisdn)) {
                throw new ApiException('Numéro Mobile Money invalide.', 'INVALID_MSISDN');
            }

            $msisdn = PhoneNumber::parse($msisdn)->e164;
        }

        $payment = $order->payments()->create([
            'provider' => $this->gateway->name(),
            'provider_reference' => 'PAY-'.Str::upper(Str::random(12)),
            'method' => $method,
            'msisdn' => $method->isMobileMoney() ? $msisdn : null,
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
        ]);

        return ['payment' => $payment, 'instructions' => $this->gateway->initiate($payment)];
    }
}
