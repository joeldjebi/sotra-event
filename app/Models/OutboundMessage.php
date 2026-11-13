<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Message WhatsApp ou notification push émis (journal ; boîte d'envoi de développement).
 *
 * @property int $id
 * @property string $channel
 * @property string $recipient
 * @property string $template
 * @property string $body
 * @property array<string, mixed>|null $meta
 * @property string $driver
 * @property string $status
 */
#[Fillable(['channel', 'recipient', 'template', 'body', 'meta', 'driver', 'status'])]
class OutboundMessage extends Model
{
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
