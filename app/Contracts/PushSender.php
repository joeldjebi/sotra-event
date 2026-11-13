<?php

namespace App\Contracts;

use App\Models\Customer;
use App\Models\Staff;

/**
 * Notifications push (Firebase Cloud Messaging) vers l'app spectateur.
 */
interface PushSender
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(Customer $customer, string $title, string $body, array $data = []): void;

    /**
     * Notification au personnel terrain (apps convoyeur et service technique), ex. nouvelle alerte urgente (ST-06).
     *
     * @param  array<string, mixed>  $data
     */
    public function sendToStaff(Staff $staff, string $title, string $body, array $data = []): void;
}
