<?php

namespace App\Integrations\Payment;

use App\Contracts\PaymentGateway;
use App\Data\PaymentInstructions;
use App\Data\RefundResult;
use App\Data\WebhookNotification;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Agrégateur simulé, en attendant la documentation de l'agrégateur d'ACCES EVENT (PA-01).
 *
 * Il reproduit le fonctionnement réel : le paiement reste « en attente » jusqu'à ce que le spectateur
 * confirme sur son téléphone — ici, sur la page /simulateur-paiement/{référence} — puis l'agrégateur
 * notifie la plateforme par un webhook signé (HMAC-SHA256 du corps, en-tête X-Signature).
 */
class FakeAggregator implements PaymentGateway
{
    public const SIGNATURE_HEADER = 'X-Signature';

    public function name(): string
    {
        return 'fake';
    }

    public function initiate(Payment $payment): PaymentInstructions
    {
        $simulatorUrl = route('payments.simulator', $payment->provider_reference);

        if ($payment->method->isMobileMoney()) {
            return new PaymentInstructions(
                message: "Une demande de confirmation de {$payment->amount} FCFA a été envoyée sur le {$payment->msisdn}. Validez-la sur votre téléphone.",
                simulatorUrl: $simulatorUrl,
            );
        }

        return new PaymentInstructions(
            message: 'Vous allez être redirigé vers la page sécurisée de paiement par carte (3-D Secure).',
            redirectUrl: $simulatorUrl,
            simulatorUrl: $simulatorUrl,
        );
    }

    public function fetchStatus(Payment $payment): PaymentStatus
    {
        $state = Cache::get(self::stateKey($payment->provider_reference));

        return is_string($state) ? PaymentStatus::from($state) : PaymentStatus::Pending;
    }

    public function parseWebhook(Request $request): WebhookNotification
    {
        $body = $request->getContent();
        $signature = (string) $request->header(self::SIGNATURE_HEADER);

        abort_unless(hash_equals(self::sign($body), $signature), 401, 'Signature de webhook invalide.');

        $payload = json_decode($body, true);
        abort_unless(is_array($payload) && isset($payload['id'], $payload['reference'], $payload['status']), 400, 'Webhook mal formé.');

        return new WebhookNotification(
            eventId: (string) $payload['id'],
            reference: (string) $payload['reference'],
            status: PaymentStatus::from((string) $payload['status']),
            payload: $payload,
            failureReason: isset($payload['reason']) ? (string) $payload['reason'] : null,
        );
    }

    /**
     * Simule la réponse du spectateur sur son téléphone et renvoie le webhook que l'agrégateur enverrait.
     *
     * @return array{body: string, signature: string}
     */
    public function simulate(Payment $payment, PaymentStatus $outcome, ?string $reason = null): array
    {
        Cache::put(self::stateKey($payment->provider_reference), $outcome->value, now()->addDay());

        $body = (string) json_encode([
            'id' => 'evt_'.Str::lower(Str::random(16)),
            'reference' => $payment->provider_reference,
            'status' => $outcome->value,
            'amount' => $payment->amount,
            'reason' => $reason,
        ]);

        return ['body' => $body, 'signature' => self::sign($body)];
    }

    public static function sign(string $body): string
    {
        return hash_hmac('sha256', $body, self::secret());
    }

    private static function secret(): string
    {
        $secret = config('eventtrans.payment.webhook_secret');

        return is_string($secret) && $secret !== '' ? $secret : hash('sha256', 'fake-aggregator|'.config('app.key'));
    }

    private static function stateKey(string $reference): string
    {
        return 'fake-payment:'.$reference;
    }

    /**
     * Simulation : Orange Money, MTN MoMo et Wave remboursent par API ; Moov Money et la carte passent
     * en traitement manuel, comme chez la plupart des agrégateurs de la sous-région.
     */
    public function refund(Payment $payment, int $amount, string $reference): RefundResult
    {
        if (in_array($payment->method, [PaymentMethod::MoovMoney, PaymentMethod::Card], true)) {
            return RefundResult::unsupported("{$payment->method->label()} : remboursement par API non disponible.");
        }

        if ($amount > $payment->amount) {
            return RefundResult::failed('Montant supérieur au paiement d\'origine.');
        }

        return RefundResult::refunded('FAKE-RF-'.Str::upper(Str::random(10)));
    }
}
