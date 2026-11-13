<?php

namespace App\Integrations\Sms;

use App\Contracts\SmsSender;
use App\Models\SmsMessage;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;

/**
 * Pilote de développement : n'envoie rien, enregistre le SMS en base et dans les logs.
 * Les messages sont consultables sur /dev/sms en local.
 */
class LogSmsSender implements SmsSender
{
    public function send(PhoneNumber $to, string $body): SmsMessage
    {
        Log::info('SMS simulé', ['to' => $to->e164, 'body' => $body]);

        return SmsMessage::query()->create([
            'to' => $to->e164,
            'body' => $body,
            'driver' => 'log',
            'status' => SmsMessage::SIMULATED,
        ]);
    }
}
