<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Auth\AuthDocs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\Customer;
use Dedoc\Scramble\Attributes\Group;

#[Group(AuthDocs::ACCOUNT, AuthDocs::ACCOUNT_DESCRIPTION, weight: 1)]
class ProfileController extends Controller
{
    /**
     * Enregistrer ou modifier mes informations (spectateur).
     *
     * Écran « Mes informations », ouvert après `POST /auth/verify` quand `next_step` vaut `complete_profile`, puis
     * depuis le profil. Le prénom et le nom sont obligatoires, l'e-mail facultatif (envoyer `null` pour l'effacer).
     * Le numéro de téléphone ne se modifie pas : c'est l'identifiant du compte. Réponse : le profil à jour, avec
     * `profile_completed: true`.
     */
    public function update(UpdateProfileRequest $request): CustomerResource
    {
        $customer = $request->user('sanctum');
        abort_unless($customer instanceof Customer, 403);

        $customer->forceFill([
            'first_name' => $request->string('first_name')->trim()->toString(),
            'last_name' => $request->string('last_name')->trim()->toString(),
            'email' => $request->filled('email') ? $request->string('email')->trim()->lower()->toString() : null,
        ])->save();

        return new CustomerResource($customer);
    }
}
