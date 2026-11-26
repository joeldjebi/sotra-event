<?php

namespace App\Models;

use App\Enums\Offer;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\CompanyLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne par défaut d'une compagnie : un lieu d'embarquement dans une commune, vers une destination (un lieu
 * d'événement, ou tous les lieux si vide), avec son nombre de bus par offre.
 *
 * @property int $id
 * @property int $company_id
 * @property string $commune_code
 * @property int|null $venue_id destination ; null : tous les lieux
 * @property string $place
 * @property Point $location
 * @property array<string, int> $bus_counts nombre de bus indexé par offre (standard, premium, gold)
 */
#[Fillable(['company_id', 'commune_code', 'venue_id', 'place', 'location', 'bus_counts'])]
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

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Lignes qui desservent un lieu d'événement : celles qui y vont et celles sans destination précise.
     *
     * @param  Builder<self>  $query
     */
    public function scopeServing(Builder $query, int $venueId): void
    {
        $query->where(fn (Builder $query) => $query->where('venue_id', $venueId)->orWhereNull('venue_id'));
    }

    public function label(): string
    {
        return "{$this->communeName()} · {$this->place}";
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
