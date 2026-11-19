<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Diagnostic de l'envoi d'e-mails : envoie un message de test avec l'expéditeur configuré (sans afficher les identifiants).
 */
#[Signature('mail:test {email : Adresse du destinataire}')]
#[Description('Envoie un e-mail de test avec la configuration MAIL_* et affiche le résultat')]
class SendTestMail extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Adresse e-mail invalide.');

            return self::INVALID;
        }

        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');

        $this->line("Envoi par « {$mailer} » ({$this->transport($mailer)}) depuis {$from}…");

        try {
            Mail::raw(
                "Bonjour,\n\nCeci est un e-mail de test envoyé par ".config('app.name').' le '.now()->format('d/m/Y à H\hi').".\nSi vous le lisez, l'envoi d'e-mails fonctionne.\n\nMerci de ne pas répondre à ce message.",
                fn (Message $message) => $message->to($email)->subject(config('app.name').' : e-mail de test'),
            );
        } catch (Throwable $exception) {
            $this->error('Échec de l\'envoi : '.$exception->getMessage());

            return self::FAILURE;
        }

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("L'e-mail n'est pas parti : MAIL_MAILER={$mailer} l'écrit seulement dans le journal. Passez à smtp.");

            return self::FAILURE;
        }

        $this->info("E-mail accepté par le serveur d'envoi. Vérifiez la boîte de réception (et les indésirables) de {$email}.");

        return self::SUCCESS;
    }

    private function transport(string $mailer): string
    {
        $config = (array) config("mail.mailers.{$mailer}");

        return isset($config['host']) ? sprintf('%s:%s', $config['host'], $config['port'] ?? '') : (string) ($config['transport'] ?? $mailer);
    }
}
