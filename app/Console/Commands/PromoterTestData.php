<?php

namespace App\Console\Commands;

use App\Enums\PromoterStatus;
use App\Models\Company;
use App\Models\Promoter;
use Database\Seeders\DemoOperationsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Données de test pour parcourir l'espace promoteur (y compris en production) : deux événements « [TEST] »
 * — l'un terminé, l'autre en cours — avec ventes, bus, scans, pannes, avis et réclamations, rattachés à un
 * promoteur et une compagnie existants. Numéros fictifs, aucun SMS réel, aucun reversement, hors catalogue public.
 */
#[Signature('demo:promoter-test {--promoter= : Nom exact du promoteur} {--company= : Nom ou slug de la compagnie (par défaut la seule active)} {--purge : Supprime les données de test} {--force : Ne pas demander de confirmation}')]
#[Description('Crée (ou supprime avec --purge) les événements [TEST] d\'un promoteur pour parcourir son espace')]
class PromoterTestData extends Command
{
    public function handle(): int
    {
        $company = $this->company();

        if ($company === null) {
            return self::FAILURE;
        }

        if ($this->option('purge')) {
            $counts = DemoOperationsSeeder::purgeTestData($company);
            $this->info("Supprimé : {$counts['events']} événements [TEST], {$counts['customers']} spectateurs fictifs, {$counts['buses']} bus et {$counts['staff']} membres du personnel de test.");

            return self::SUCCESS;
        }

        $promoter = Promoter::query()->where('name', (string) $this->option('promoter'))->first();

        if ($promoter === null) {
            $this->error('Promoteur introuvable. Indiquez son nom exact : --promoter="Promoteur Test". Promoteurs : '.Promoter::query()->orderBy('name')->pluck('name')->join(', '));

            return self::FAILURE;
        }

        if ($promoter->status !== PromoterStatus::Approved) {
            $this->error("« {$promoter->name} » n'est pas validé : son espace est fermé.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Créer les événements [TEST] pour « {$promoter->name} » avec la compagnie « {$company->name} » ?", true)) {
            return self::FAILURE;
        }

        DemoOperationsSeeder::forTesting($company, $promoter)->setCommand($this)->run();

        $this->newLine();
        $this->info('Données de test créées. Elles n\'apparaissent pas sur le site public.');
        $this->line('Suppression : php artisan demo:promoter-test --purge'.($this->option('company') ? ' --company="'.$this->option('company').'"' : ''));

        return self::SUCCESS;
    }

    private function company(): ?Company
    {
        $name = $this->option('company');

        if (is_string($name) && $name !== '') {
            $company = Company::query()->where('slug', $name)->orWhere('name', $name)->first();

            if ($company === null) {
                $this->error("Compagnie « {$name} » introuvable.");
            }

            return $company;
        }

        $companies = Company::query()->where('is_active', true)->get();

        if ($companies->count() !== 1) {
            $this->error('Indiquez la compagnie : --company="…". Compagnies actives : '.($companies->pluck('name')->join(', ') ?: 'aucune'));

            return null;
        }

        return $companies->first();
    }
}
