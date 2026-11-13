<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\Space;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Utilisateur des espaces web : ACCES EVENT, compagnie ou promoteur.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property int|null $company_id
 * @property int|null $promoter_id
 * @property bool $is_active
 * @property-read Company|null $company
 * @property-read Promoter|null $promoter
 */
#[Fillable(['name', 'email', 'phone', 'password', 'company_id', 'promoter_id', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /** @var list<string> */
    public array $auditExclude = ['last_login_at', 'two_factor_confirmed_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Promoter, $this>
     */
    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class);
    }

    /**
     * Rôles métier de l'utilisateur, dans l'ordre de l'enum.
     *
     * @return list<Role>
     */
    public function businessRoles(): array
    {
        $names = $this->getRoleNames()->all();

        return array_values(array_filter(Role::cases(), fn (Role $role): bool => in_array($role->value, $names, true)));
    }

    public function primaryRole(): ?Role
    {
        return $this->businessRoles()[0] ?? null;
    }

    public function space(): ?Space
    {
        return $this->primaryRole()?->space();
    }

    public function belongsToSpace(Space $space): bool
    {
        return $this->space() === $space;
    }

    public function mustEnrollTwoFactor(): bool
    {
        return (bool) config('eventtrans.two_factor.enforced') && ! $this->hasEnabledTwoFactorAuthentication();
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return mb_strtoupper(implode('', array_map(fn (string $part): string => mb_substr($part, 0, 1), array_slice($parts, 0, 2))));
    }
}
