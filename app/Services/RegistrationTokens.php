<?php

namespace App\Services;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Jeton d'inscription remis après la vérification du code SMS d'un nouveau spectateur.
 *
 * Il prouve seulement que le numéro a été vérifié : valable 15 minutes, utilisable une fois, il permet de créer
 * le compte avec ses informations (POST /auth/register/complete). Seule son empreinte est conservée (cache).
 */
class RegistrationTokens
{
    public const TTL_MINUTES = 15;

    public function issue(PhoneNumber $phone): string
    {
        $token = Str::random(64);
        Cache::put($this->key($token), $phone->e164, now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /**
     * Consomme le jeton et renvoie le numéro vérifié (format international), ou null s'il est inconnu ou expiré.
     */
    public function consume(string $token): ?string
    {
        $phone = Cache::pull($this->key($token));

        return is_string($phone) ? $phone : null;
    }

    private function key(string $token): string
    {
        return 'registration-token:'.hash('sha256', $token);
    }
}
