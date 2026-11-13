<?php

namespace App\Models;

use App\Enums\StaffRole;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Personnel terrain d'une compagnie : chauffeur, convoyeur ou technicien.
 *
 * @property int $id
 * @property int $company_id
 * @property StaffRole $role
 * @property string $first_name
 * @property string $last_name
 * @property string $phone
 * @property string|null $employee_number
 * @property bool $is_active
 * @property int|null $tech_team_id
 * @property-read TechTeam|null $techTeam
 */
#[Fillable(['company_id', 'tech_team_id', 'role', 'first_name', 'last_name', 'phone', 'employee_number', 'is_active', 'last_login_at'])]
class Staff extends Authenticatable
{
    /** @use HasFactory<StaffFactory> */
    use Auditable, BelongsToCompany, HasApiTokens, HasFactory;

    protected $table = 'staff';

    /** @var list<string> */
    public array $auditExclude = ['last_login_at'];

    protected function casts(): array
    {
        return [
            'role' => StaffRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function canSignIn(): bool
    {
        return $this->is_active && $this->role->canSignIn();
    }

    /**
     * Nom abrégé affiché aux passagers et collègues (ex. « M. Bamba »).
     */
    public function shortName(): string
    {
        return mb_substr($this->first_name, 0, 1).'. '.$this->last_name;
    }

    /**
     * @return BelongsTo<TechTeam, $this>
     */
    public function techTeam(): BelongsTo
    {
        return $this->belongsTo(TechTeam::class);
    }

    public function fullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }
}
