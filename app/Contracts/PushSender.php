<?php

namespace App\Contracts;

use App\Models\Customer;

/**
 * Notifications push (Firebase Cloud Messaging) vers l'app spectateur.
 */
interface PushSender
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(Customer $customer, string $title, string $body, array $data = []): void;
}
