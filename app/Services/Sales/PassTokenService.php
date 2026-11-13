<?php

namespace App\Services\Sales;

use App\Models\Pass;
use SodiumException;

/**
 * Jeton signé encodé dans le QR du Pass (NF-01) : vérifiable hors ligne par l'app convoyeur
 * avec la seule clé publique. Format : « ET1.<charge utile base64url>.<signature Ed25519 base64url> ».
 *
 * La charge utile est volontairement courte (QR lisible) : p = id du Pass, e = événement, b = point,
 * o = offre, t = type, v = version (incrémentée à chaque transfert : l'ancien QR devient invalide, RG-04).
 */
class PassTokenService
{
    private const PREFIX = 'ET1';

    public function sign(Pass $pass): string
    {
        $payload = self::base64UrlEncode((string) json_encode([
            'p' => $pass->id,
            'e' => $pass->event_id,
            'b' => $pass->boarding_point_id,
            'o' => $pass->offer->value,
            't' => $pass->pass_type->value,
            'v' => $pass->token_version,
        ]));

        $signature = sodium_crypto_sign_detached(self::PREFIX.'.'.$payload, $this->secretKey());

        return self::PREFIX.'.'.$payload.'.'.self::base64UrlEncode($signature);
    }

    /**
     * Vérifie la signature et renvoie la charge utile, ou null si le jeton est falsifié ou mal formé.
     *
     * @return array{p: int, e: int, b: int, o: string, t: string, v: int}|null
     */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            return null;
        }

        $signature = self::base64UrlDecode($parts[2]);

        try {
            if ($signature === '' || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
                || ! sodium_crypto_sign_verify_detached($signature, $parts[0].'.'.$parts[1], $this->publicKey())) {
                return null;
            }
        } catch (SodiumException) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($parts[1]), true);

        return is_array($payload) && isset($payload['p'], $payload['e'], $payload['b'], $payload['o'], $payload['t'], $payload['v'])
            ? $payload
            : null;
    }

    /**
     * Clé publique (base64) à embarquer dans l'app convoyeur pour la vérification hors ligne.
     */
    public function publicKeyBase64(): string
    {
        return base64_encode($this->publicKey());
    }

    private function publicKey(): string
    {
        return sodium_crypto_sign_publickey($this->keyPair());
    }

    private function secretKey(): string
    {
        return sodium_crypto_sign_secretkey($this->keyPair());
    }

    private function keyPair(): string
    {
        $configured = config('eventtrans.pass_signing.secret_key');

        if (is_string($configured) && $configured !== '') {
            $secret = base64_decode($configured, true);

            return sodium_crypto_sign_keypair_from_secretkey_and_publickey($secret, sodium_crypto_sign_publickey_from_secretkey($secret));
        }

        // Repli : paire déterministe dérivée de APP_KEY (stable tant que la clé d'application ne change pas).
        $seed = hash('sha256', 'eventtrans-pass-signing|'.config('app.key'), true);

        return sodium_crypto_sign_seed_keypair($seed);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
