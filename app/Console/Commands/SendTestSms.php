<?php

namespace App\Console\Commands;

use App\Integrations\Sms\MtargetSmsSender;
use App\Support\PhoneNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;

/**
 * Diagnostic de l'agrégateur MTarget : envoie un SMS et affiche la réponse brute (sans les identifiants).
 */
#[Signature('sms:test {phone : Numéro du destinataire, ex. 0700000000 ou +2250700000000} {--message= : Texte du SMS} {--sender= : Expéditeur, à défaut MTARGET_SENDER}')]
#[Description('Envoie un SMS de test par MTarget et affiche la réponse de l\'agrégateur')]
class SendTestSms extends Command
{
    public function handle(): int
    {
        try {
            $phone = PhoneNumber::parse((string) $this->argument('phone'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        if (config('services.mtarget.username') === null || config('services.mtarget.password') === null || config('services.mtarget.url') === null) {
            $this->error('MTarget n\'est pas configuré : renseignez MTARGET_URL, MTARGET_USERNAME et MTARGET_PASSWORD dans .env.');

            return self::FAILURE;
        }

        $message = (string) ($this->option('message') ?: 'EVENT TRANS : SMS de test envoyé le '.now()->format('d/m/Y à H\hi').'.');
        $sender = $this->option('sender') ? (string) $this->option('sender') : null;

        try {
            $result = MtargetSmsSender::fromConfig()->sendDetailed($message, $phone->e164, $sender);
        } catch (ConnectionException) {
            $this->error('MTarget est injoignable (erreur réseau ou délai dépassé).');

            return self::FAILURE;
        }

        $first = $result['results'][0] ?? [];
        $this->table(['Destinataire', 'Expéditeur', 'HTTP', 'Code', 'Motif', 'Ticket', 'Segments'], [[
            $phone->masked(), $result['sender'], $result['http_status'], $first['code'] ?? '—', $first['reason'] ?? '—', $first['ticket'] ?? '—', $first['smscount'] ?? '—',
        ]]);

        if ($result['http_status'] !== 200 || ! MtargetSmsSender::isAccepted($first)) {
            $this->line('Réponse brute : '.mb_substr($result['raw_body'], 0, 500));
            $this->error('SMS refusé par MTarget.');

            return self::FAILURE;
        }

        $this->info('SMS accepté par MTarget.');

        return self::SUCCESS;
    }
}
