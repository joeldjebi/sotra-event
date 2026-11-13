<?php

namespace App\Integrations\Sms;

use App\Contracts\SmsSender;
use App\Models\SmsMessage;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi des SMS par l'API HTTP de MTarget (SMS_DRIVER=mtarget).
 *
 * Réponse attendue : {"results":[{"msisdn":"+225…","smscount":"1","code":"0","reason":"ACCEPTED","ticket":"…"}]}.
 * « ACCEPTED » signifie seulement que MTarget a pris le message en charge (statut « accepted », ticket conservé
 * dans provider_reference). Chaque SMS est tracé dans sms_messages ; l'identifiant est transmis à MTarget
 * (remoteid) pour rapprocher les futurs accusés de réception.
 *
 * Un refus ou une panne réseau ne lève pas d'exception : le SMS est marqué « failed » avec son motif, pour ne pas
 * interrompre un paiement ou une notification de masse. Les flux qui en dépendent (code OTP) testent ce statut.
 * Les identifiants, l'URL complète et le corps de la requête ne sont jamais écrits dans les journaux.
 */
class MtargetSmsSender implements SmsSender
{
    public function __construct(
        private readonly string $url,
        private readonly string $username,
        private readonly string $password,
        private readonly string $defaultSender,
        private readonly int $timeout = 30,
        private readonly bool $verifySsl = true,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            url: (string) config('services.mtarget.url'),
            username: (string) config('services.mtarget.username'),
            password: (string) config('services.mtarget.password'),
            defaultSender: (string) config('services.mtarget.sender'),
            timeout: (int) config('services.mtarget.timeout'),
            verifySsl: (bool) config('services.mtarget.verify_ssl'),
        );
    }

    public function send(PhoneNumber $to, string $body): SmsMessage
    {
        $message = SmsMessage::query()->create(['to' => $to->e164, 'body' => $body, 'driver' => 'mtarget', 'status' => SmsMessage::FAILED]);

        if ($this->url === '' || $this->username === '' || $this->password === '') {
            Log::error('[SMS MTarget] non configuré', ['to' => $to->masked()]);

            return $this->fail($message, 'Identifiants MTarget non configurés.');
        }

        try {
            $result = $this->sendDetailed($body, $to->e164, remoteId: (string) $message->id);
        } catch (ConnectionException $exception) {
            // Le message de Guzzle peut contenir l'URL : on ne garde que la nature de l'erreur.
            Log::warning('[SMS MTarget] injoignable', ['to' => $to->masked(), 'error' => class_basename($exception)]);

            return $this->fail($message, 'MTarget injoignable (erreur réseau ou délai dépassé).');
        }

        $first = $result['results'][0] ?? [];

        if ($result['http_status'] !== 200 || ! self::isAccepted($first)) {
            Log::warning('[SMS MTarget] refusé', [
                'to' => $to->masked(),
                'http_status' => $result['http_status'],
                'code' => $first['code'] ?? null,
                'reason' => $first['reason'] ?? null,
            ]);

            return $this->fail($message, 'Refusé par MTarget (HTTP '.$result['http_status'].', '.($first['reason'] ?? 'réponse inattendue').').');
        }

        Log::info('[SMS MTarget] accepté', ['to' => $to->masked(), 'ticket' => $first['ticket'] ?? null, 'smscount' => $first['smscount'] ?? null]);

        $message->update([
            'status' => SmsMessage::ACCEPTED,
            'provider_reference' => isset($first['ticket']) ? (string) $first['ticket'] : null,
            'segments' => isset($first['smscount']) ? (int) $first['smscount'] : null,
        ]);

        return $message;
    }

    /**
     * Envoi brut, sans interprétation de la réponse (commande sms:test, diagnostic).
     *
     * @return array{http_status: int, raw_body: string, body: array<string, mixed>|null, results: list<array<string, mixed>>, msisdn: string, sender: string}
     *
     * @throws ConnectionException
     */
    public function sendDetailed(string $message, string $msisdn, ?string $sender = null, ?string $remoteId = null): array
    {
        $msisdn = self::formatMsisdn($msisdn);
        $sender ??= $this->defaultSender;

        $params = array_filter([
            'username' => $this->username,
            'password' => $this->password,
            'msisdn' => $msisdn,
            'msg' => $message,
            'sender' => $sender,
            'remoteid' => $remoteId,
        ], fn (?string $value): bool => $value !== null);

        $response = Http::asForm()
            ->timeout($this->timeout)
            ->withOptions(['verify' => $this->verifySsl])
            ->post($this->url, $params);

        $body = $response->json();
        $body = is_array($body) ? $body : null;
        $results = is_array($body['results'] ?? null) ? array_values(array_filter($body['results'], 'is_array')) : [];

        return [
            'http_status' => $response->status(),
            'raw_body' => $response->body(),
            'body' => $body,
            'results' => $results,
            'msisdn' => $msisdn,
            'sender' => $sender,
        ];
    }

    /**
     * Format international attendu par MTarget : +2250700000000.
     */
    public static function formatMsisdn(string $msisdn): string
    {
        $msisdn = (string) preg_replace('/[\s().-]+/', '', trim($msisdn));

        return str_starts_with($msisdn, '+') ? $msisdn : '+'.$msisdn;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function isAccepted(array $result): bool
    {
        return (string) ($result['code'] ?? '') === '0' && strtoupper((string) ($result['reason'] ?? '')) === 'ACCEPTED';
    }

    private function fail(SmsMessage $message, string $error): SmsMessage
    {
        $message->update(['status' => SmsMessage::FAILED, 'error' => $error]);

        return $message;
    }
}
