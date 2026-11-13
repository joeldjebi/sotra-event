<?php

namespace App\Console\Commands;

use App\Services\Operations\DelayMonitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Départs non confirmés plus de 20 min après l'heure prévue : réclamation collective et passagers prévenus.
 * Planifiée toutes les 5 minutes (les départs tardifs confirmés sont traités dès la confirmation, CV-14).
 */
#[Signature('missions:check-delays')]
#[Description('Ouvre une réclamation collective pour les bus en retard de plus de 20 minutes')]
class CheckMissionDelays extends Command
{
    public function handle(DelayMonitor $monitor): int
    {
        $opened = $monitor->sweep();
        $this->info("{$opened} réclamation(s) collective(s) ouverte(s).");

        return self::SUCCESS;
    }
}
