<?php

namespace App\Contracts;

use App\Data\PaymentInstructions;
use App\Data\RefundResult;
use App\Data\WebhookNotification;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Agrégateur de paiement (PA-01). Le paiement n'est confirmé que par le webhook signé (PA-02)
 * ou par la réconciliation (PA-03), jamais par le retour du navigateur ou de l'app.
 */
interface PaymentGateway
{
    public function name(): string;

    public function initiate(Payment $payment): PaymentInstructions;

    /**
     * Statut connu de l'agrégateur, pour la tâche de réconciliation.
     */
    public function fetchStatus(Payment $payment): PaymentStatus;

    /**
     * Vérifie la signature et décode le webhook. Lève une exception HTTP 401 si la signature est invalide.
     */
    public function parseWebhook(Request $request): WebhookNotification;

    /**
     * Rembourse tout ou partie d'un paiement sur le moyen d'origine (PA-04), si l'opérateur le permet.
     */
    public function refund(Payment $payment, int $amount, string $reference): RefundResult;
}
