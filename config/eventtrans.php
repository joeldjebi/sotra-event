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

    /*
    | Événements : zones tarifaires (RG-06), grille par défaut (exemple du CDC, AD-03), horaires.
    */
    'events' => [
        // Bornes supérieures en km (zone 3 = au-delà de 25 km).
        'zones' => [1 => 15, 2 => 25, 3 => null],
        'default_fee_rate' => 2,
        // Délai entre la convocation du spectateur et le départ du bus (minutes).
        'boarding_lead_minutes' => 15,
        // Vitesse moyenne utilisée pour estimer la durée de trajet tant que l'itinéraire n'est pas calculé.
        'estimated_speed_kmh' => 30,
        'default_price_grid' => [
            'aller_retour' => [
                1 => ['standard' => 3000, 'premium' => 5000, 'gold' => 10000],
                2 => ['standard' => 3500, 'premium' => 5500, 'gold' => 10000],
                3 => ['standard' => 4000, 'premium' => 6000, 'gold' => 10000],
            ],
            'aller_simple' => [1 => ['standard' => 2000]],
            'retour_simple' => [1 => ['standard' => 2000]],
        ],
    ],

    /*
    | Communes desservies : code à 3 lettres (utilisé dans le code du Pass, RG-02) => nom.
    */
    'communes' => [
        'ABO' => 'Abobo',
        'ADJ' => 'Adjamé',
        'ANY' => 'Anyama',
        'ATT' => 'Attécoubé',
        'BIN' => 'Bingerville',
        'COC' => 'Cocody',
        'KOU' => 'Koumassi',
        'MAR' => 'Marcory',
        'PLA' => 'Plateau',
        'PBT' => 'Port-Bouët',
        'SON' => 'Songon',
        'TRE' => 'Treichville',
        'YOP' => 'Yopougon',
    ],

    /*
    | Vente des Pass.
    */
    'sales' => [
        // Places bloquées pendant le paiement (§ 11 CDC, statut « en attente de paiement »).
        'hold_minutes' => 15,
        // Limite de Pass par commande (SP-06, décision du 13/11).
        'max_passes_per_order' => 10,
    ],

    /*
    | Paiement. Le pilote « fake » simule l'agrégateur (attente, confirmation, webhook signé).
    */
    'payment' => [
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
        // Délai après lequel un paiement en attente est vérifié auprès de l'agrégateur (PA-03).
        'reconcile_after_minutes' => 2,
    ],

    /*
    | Signature des QR des Pass (NF-01). Sans clé configurée, une paire Ed25519 est dérivée de APP_KEY.
    */
    'pass_signing' => [
        'secret_key' => env('PASS_SIGNING_SECRET_KEY'),
    ],

];
