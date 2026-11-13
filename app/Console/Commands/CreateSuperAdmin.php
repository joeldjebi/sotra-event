<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Crée le premier super administrateur ACCES EVENT lors de l'installation (le mot de passe est saisi, jamais
 * passé en argument). La double authentification est activée à sa première connexion.
 */
#[Signature('users:create-admin {email : Adresse e-mail} {name : Nom affiché}')]
#[Description('Crée un super administrateur ACCES EVENT')]
class CreateSuperAdmin extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = (string) $this->secret('Mot de passe (12 caractères au moins, lettres et chiffres)');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => (string) $this->argument('name'),
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
        $user->assignRole(Role::SuperAdmin->value);
        AuditLog::record('user_created', $user, [], ['role' => Role::SuperAdmin->value, 'via' => 'console']);

        $this->info("Super administrateur {$email} créé. Il activera sa double authentification à la première connexion.");

        return self::SUCCESS;
    }
}
