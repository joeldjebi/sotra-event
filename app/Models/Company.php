<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use App\Models\Concerns\Auditable;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Compagnie de transport partenaire.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $default_commission_rate
 * @property string|null $legal_name
 * @property string|null $contract_reference
 * @property Carbon|null $contract_starts_on
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property PayoutMethod|null $payout_method
 * @property array<string, string>|null $payout_details
 * @property bool $is_active
 */
#[Fillable(['name', 'slug', 'legal_name', 'contract_reference', 'contract_starts_on', 'email', 'phone', 'address', 'default_commission_rate', 'payout_method', 'payout_details', 'is_active'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Auditable, HasFactory;

    /** @var list<string> */
    public array $auditMasked = ['payout_details'];

    protected function casts(): array
    {
        return [
            'default_commission_rate' => 'decimal:2',
            'contract_starts_on' => 'date',
            'payout_method' => PayoutMethod::class,
            'payout_details' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Staff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    /**
     * @return HasMany<Bus, $this>
     */
    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class);
    }
}
