<?php

namespace App\Models;

use App\Enums\BusCategory;
use App\Enums\BusStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\BusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bus d'une compagnie.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $depot_id
 * @property string $number
 * @property string $plate
 * @property int $capacity
 * @property BusCategory $category
 * @property BusStatus $status
 * @property string|null $notes
 * @property-read Depot|null $depot
 */
#[Fillable(['company_id', 'depot_id', 'number', 'plate', 'capacity', 'category', 'status', 'notes'])]
class Bus extends Model
{
    /** @use HasFactory<BusFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected $table = 'buses';

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'category' => BusCategory::class,
            'status' => BusStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Depot, $this>
     */
    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }
}
