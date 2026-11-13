<?php

namespace App\Integrations\Messaging;

use App\Contracts\WhatsAppSender;
use App\Models\OutboundMessage;
use App\Support\PhoneNumber;

/**
 * Pilote simulé : enregistre le message WhatsApp sans l'envoyer (consultable sur /dev/sms en local).
 */
class LogWhatsAppSender implements WhatsAppSender
{
    public function send(PhoneNumber $to, string $template, string $body, array $meta = []): void
    {
        OutboundMessage::query()->create([
            'channel' => 'whatsapp',
            'recipient' => $to->e164,
            'template' => $template,
            'body' => $body,
            'meta' => $meta,
            'driver' => 'log',
            'status' => 'simulated',
        ]);
    }
}
