<?php

return [

    /*
    | Double authentification obligatoire pour les espaces web (NF-03).
    */
    'two_factor' => [
        'enforced' => (bool) env('TWO_FACTOR_ENFORCED', true),
    ],

    /*
    | Codes OTP (spectateurs, convoyeurs, techniciens).
    */
    'otp' => [
        'length' => 6,
        'ttl_minutes' => 5,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
    ],

    /*
    | Indicatif par défaut pour les numéros saisis sans indicatif (Côte d'Ivoire).
    */
    'default_country_code' => '225',

    /*
    | Pilotes des services externes (fake / log tant que les fournisseurs ne sont pas choisis).
    */
    'drivers' => [
        'sms' => env('SMS_DRIVER', 'log'),
        'payment' => env('PAYMENT_DRIVER', 'fake'),
        'whatsapp' => env('WHATSAPP_DRIVER', 'log'),
        'push' => env('PUSH_DRIVER', 'log'),
    ],

    'sms' => [
        'sender_name' => env('SMS_SENDER_NAME', 'EVENTTRANS'),
    ],

];
