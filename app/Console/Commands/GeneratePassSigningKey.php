<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Génère la clé privée Ed25519 de signature des QR des Pass (NF-01), à placer dans PASS_SIGNING_SECRET_KEY.
 * Sans elle, la clé est dérivée de APP_KEY. La clé publique est diffusée aux terminaux dans le manifeste.
 * Changer de clé invalide tous les QR déjà émis : à faire avant l'ouverture des ventes.
 */
#[Signature('passes:generate-signing-key')]
#[Description('Génère la clé de signature Ed25519 des QR (à copier dans PASS_SIGNING_SECRET_KEY)')]
class GeneratePassSigningKey extends Command
{
    public function handle(): int
    {
        $keyPair = sodium_crypto_sign_keypair();

        $this->line('PASS_SIGNING_SECRET_KEY='.base64_encode(sodium_crypto_sign_secretkey($keyPair)));
        $this->line('Clé publique (pour contrôle) : '.base64_encode(sodium_crypto_sign_publickey($keyPair)));
        $this->warn('Conservez cette clé hors du serveur. La changer invalide tous les QR déjà émis.');

        return self::SUCCESS;
    }
}
