<?php

namespace App\Livewire\Concerns;

use App\Actions\Users\SetInitialPassword;
use App\Models\User;

/**
 * Identifiants donnés par l'administrateur : génération d'un mot de passe initial et affichage unique
 * des identifiants à transmettre (vue partials.issued-credentials).
 */
trait IssuesInitialPasswords
{
    /** @var array{name: string, email: string, password: string}|null identifiants à transmettre, affichés une seule fois */
    public ?array $issuedCredentials = null;

    /**
     * Remplit le champ de mot de passe indiqué avec un mot de passe généré.
     */
    public function generatePassword(string $field = 'initial_password'): void
    {
        abort_unless(in_array($field, $this->passwordFields(), true), 400);
        $this->{$field} = SetInitialPassword::generate();
    }

    public function dismissCredentials(): void
    {
        $this->issuedCredentials = null;
    }

    protected function issueCredentials(User $user, string $password): void
    {
        app(SetInitialPassword::class)->handle($user, $password);
        $this->issuedCredentials = ['name' => $user->name, 'email' => $user->email, 'password' => $password];
    }

    /**
     * Champs du composant qui peuvent recevoir un mot de passe généré.
     *
     * @return list<string>
     */
    abstract protected function passwordFields(): array;
}
