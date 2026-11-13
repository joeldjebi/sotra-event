<?php

namespace App\Contracts;

use App\Support\PhoneNumber;

/**
 * Envoi WhatsApp (modèles de messages validés par Meta, WA-05).
 *
 * @phpstan-type Meta array<string, mixed>
 */
interface WhatsAppSender
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function send(PhoneNumber $to, string $template, string $body, array $meta = []): void;
}
