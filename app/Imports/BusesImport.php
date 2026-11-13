<?php

namespace App\Imports;

use App\Enums\BusCategory;
use App\Enums\BusStatus;
use App\Models\Bus;
use App\Models\Depot;
use Illuminate\Support\Str;

/**
 * Import de la flotte : numero, immatriculation, capacite, gamme, statut, depot.
 * Une immatriculation déjà connue dans la compagnie met le bus à jour.
 */
class BusesImport extends RowImport
{
    public static function headings(): array
    {
        return ['numero', 'immatriculation', 'capacite', 'gamme', 'statut', 'depot'];
    }

    public static function example(): array
    {
        return ['12', '1234 AB 01', 50, 'Standard', 'En service', 'Dépôt de Yopougon'];
    }

    protected function rules(): array
    {
        return [
            'numero' => ['required'],
            'immatriculation' => ['required', 'string', 'max:30'],
            'capacite' => ['required', 'integer', 'between:10,200'],
            'gamme' => ['nullable', 'string'],
            'statut' => ['nullable', 'string'],
            'depot' => ['nullable', 'string'],
        ];
    }

    protected function check(array $row): ?string
    {
        if (($row['gamme'] ?? '') !== '' && $row['gamme'] !== null && self::matchOption($row['gamme'], BusCategory::options()) === null) {
            return "gamme « {$row['gamme']} » inconnue (Standard, Premium ou Gold).";
        }

        if (($row['statut'] ?? '') !== '' && $row['statut'] !== null && self::matchOption($row['statut'], BusStatus::options()) === null) {
            return "statut « {$row['statut']} » inconnu.";
        }

        if (($row['depot'] ?? '') !== '' && $row['depot'] !== null && $this->depotId((string) $row['depot']) === null) {
            return "dépôt « {$row['depot']} » introuvable. Créez-le d'abord dans Dépôts.";
        }

        $owner = Bus::query()->withoutGlobalScopes()->where('plate', $this->plate($row))->value('company_id');

        if ($owner !== null && (int) $owner !== $this->companyId) {
            return 'cette immatriculation appartient à une autre compagnie.';
        }

        return null;
    }

    protected function persist(array $row): bool
    {
        $bus = Bus::query()->withoutGlobalScopes()->updateOrCreate(
            ['plate' => $this->plate($row)],
            [
                'company_id' => $this->companyId,
                'number' => (string) $row['numero'],
                'capacity' => (int) $row['capacite'],
                'category' => self::matchOption($row['gamme'] ?? 'standard', BusCategory::options()) ?? BusCategory::Standard->value,
                'status' => self::matchOption($row['statut'] ?? 'en_service', BusStatus::options()) ?? BusStatus::InService->value,
                'depot_id' => ($row['depot'] ?? null) ? $this->depotId((string) $row['depot']) : null,
            ],
        );

        return $bus->wasRecentlyCreated;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function plate(array $row): string
    {
        return Str::upper((string) preg_replace('/\s+/', ' ', (string) $row['immatriculation']));
    }

    private function depotId(string $name): ?int
    {
        $id = Depot::query()->withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
