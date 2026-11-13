<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Tous les tests Feature utilisent la base PostgreSQL de test, réinitialisée par transaction.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Utilisateur web avec un rôle, 2FA déjà activée (sauf demande contraire).
 *
 * @param  array<string, mixed>  $attributes
 */
function userWithRole(Role $role, array $attributes = [], bool $withTwoFactor = true): User
{
    $factory = User::factory()->withRole($role);

    if ($withTwoFactor) {
        $factory = $factory->withTwoFactor();
    }

    return $factory->create($attributes);
}
