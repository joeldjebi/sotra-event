<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\DepotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dépôt d'une compagnie.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $address
 * @property string|null $phone
 * @property Point|null $location
 */
#[Fillable(['company_id', 'name', 'address', 'phone', 'location'])]
class Depot extends Model
{
    /** @use HasFactory<DepotFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'location' => Point::class,
        ];
    }

    /**
     * @return HasMany<Bus, $this>
     */
    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class);
    }
}
