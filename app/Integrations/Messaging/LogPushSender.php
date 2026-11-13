<?php

namespace App\Integrations\Messaging;

use App\Contracts\PushSender;
use App\Models\Customer;
use App\Models\OutboundMessage;

/**
 * Pilote simulé : enregistre la notification push sans l'envoyer.
 */
class LogPushSender implements PushSender
{
    public function send(Customer $customer, string $title, string $body, array $data = []): void
    {
        OutboundMessage::query()->create([
            'channel' => 'push',
            'recipient' => 'customer:'.$customer->id,
            'template' => $title,
            'body' => $body,
            'meta' => $data,
            'driver' => 'log',
            'status' => 'simulated',
        ]);
    }
}
