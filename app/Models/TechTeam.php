<?php

namespace App\Models;

use App\Enums\StaffRole;
use App\Enums\TechTeamStatus;
use App\Enums\TechTeamType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\TechTeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Équipe du service technique (ex. « Équipe T3 · dépannage léger »).
 *
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property TechTeamType $type
 * @property TechTeamStatus $status
 * @property int|null $depot_id
 * @property Point|null $location
 * @property Carbon|null $located_at
 */
#[Fillable(['company_id', 'code', 'type', 'status', 'depot_id', 'location', 'located_at'])]
class TechTeam extends Model
{
    /** @use HasFactory<TechTeamFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    /** @var list<string> */
    public array $auditExclude = ['location', 'located_at'];

    protected function casts(): array
    {
        return [
            'type' => TechTeamType::class,
            'status' => TechTeamStatus::class,
            'location' => Point::class,
            'located_at' => 'datetime',
        ];
    }

    /**
     * Techniciens membres de l'équipe.
     *
     * @return HasMany<Staff, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Staff::class)->where('role', StaffRole::Technician);
    }

    /**
     * @return BelongsTo<Depot, $this>
     */
    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }
}
