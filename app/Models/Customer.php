<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Spectateur, identifié par son téléphone : mot de passe dans l'app (choisi après un code SMS), code SMS sur le site web.
 *
 * @property int $id
 * @property string $phone
 * @property string|null $password
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string $locale
 * @property Carbon|null $anonymized_at
 */
#[Fillable(['phone', 'first_name', 'last_name', 'email', 'locale', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasApiTokens, HasFactory;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    /**
     * Profil rempli après la première connexion (écran « Mes informations » de l'app) : prénom et nom.
     */
    public function hasCompletedProfile(): bool
    {
        return filled($this->first_name) && filled($this->last_name);
    }

    public function fullName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    /**
     * Pass dont le spectateur est détenteur (achetés ou reçus).
     *
     * @return HasMany<Pass, $this>
     */
    public function passes(): HasMany
    {
        return $this->hasMany(Pass::class, 'holder_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
