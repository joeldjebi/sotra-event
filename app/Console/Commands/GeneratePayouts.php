<?php

namespace App\Console\Commands;

use App\Services\Finance\PayoutCalculator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Relevés de reversement (PA-05) : chaque nuit, les événements terminés sont clos et leur relevé calculé
 * (ou recalculé tant qu'il n'est pas validé dans la console).
 */
#[Signature('payouts:generate')]
#[Description('Clôt les événements terminés et calcule les relevés de reversement')]
class GeneratePayouts extends Command
{
    public function handle(PayoutCalculator $calculator): int
    {
        $this->info("{$calculator->run()} relevé(s) calculé(s).");

        return self::SUCCESS;
    }
}
