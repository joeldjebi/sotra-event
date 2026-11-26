<?php

namespace App\Models;

use App\Enums\Offer;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\CompanyLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ligne par défaut d'une compagnie dans une commune : lieu d'embarquement habituel et nombre de bus par offre.
 *
 * @property int $id
 * @property int $company_id
 * @property string $commune_code
 * @property string $place
 * @property Point $location
 * @property array<string, int> $bus_counts nombre de bus indexé par offre (standard, premium, gold)
 */
#[Fillable(['company_id', 'commune_code', 'place', 'location', 'bus_counts'])]
class CompanyLine extends Model
{
    /** @use HasFactory<CompanyLineFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'location' => Point::class,
            'bus_counts' => 'array',
        ];
    }

    public function communeName(): string
    {
        return (string) (config('eventtrans.communes')[$this->commune_code] ?? $this->commune_code);
    }

    public function busesFor(Offer $offer): int
    {
        return (int) ($this->bus_counts[$offer->value] ?? 0);
    }
}
