<?php

namespace App\Contracts;

use App\Models\SmsMessage;
use App\Support\PhoneNumber;

/**
 * Envoi de SMS. L'implémentation dépend de config('eventtrans.drivers.sms').
 */
interface SmsSender
{
    public function send(PhoneNumber $to, string $body): SmsMessage;
}
