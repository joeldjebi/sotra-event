<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\Space;
use App\Models\Company;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Attribue un rôle ; rattache une compagnie ou un promoteur si le rôle l'exige.
     */
    public function withRole(Role $role): static
    {
        return $this
            ->state(fn (array $attributes) => match ($role->space()) {
                Space::Company => ['company_id' => $attributes['company_id'] ?? Company::factory()],
                Space::Promoter => ['promoter_id' => $attributes['promoter_id'] ?? Promoter::factory()],
                Space::Admin => [],
            })
            ->afterCreating(fn (User $user) => $user->assignRole($role->value));
    }

    /**
     * Double authentification activée et confirmée.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt((string) json_encode(Collection::times(8, fn () => RecoveryCode::generate())->all())),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
