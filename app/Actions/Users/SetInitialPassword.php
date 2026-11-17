<?php

namespace App\Actions\Users;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Mot de passe initial donné par l'administrateur : l'utilisateur devra le remplacer à sa première connexion.
 * Le mot de passe n'est jamais journalisé.
 */
class SetInitialPassword
{
    public function handle(User $user, string $password): void
    {
        $user->forceFill(['password' => Hash::make($password), 'must_change_password' => true])->save();
        AuditLog::record('initial_password_set', $user);
    }

    /**
     * Mot de passe lisible et conforme à la politique (12 caractères, lettres et chiffres, sans caractères ambigus).
     */
    public static function generate(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
        $digits = '23456789';
        $password = '';

        for ($i = 0; $i < 10; $i++) {
            $password .= $letters[random_int(0, strlen($letters) - 1)];
        }

        return $password.$digits[random_int(0, 7)].$digits[random_int(0, 7)];
    }
}
