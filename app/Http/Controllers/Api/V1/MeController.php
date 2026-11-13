<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Resources\Api\V1\StaffResource;
use App\Models\Customer;
use App\Models\Staff;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group('Authentification', 'Connexion par code SMS, jeton par appareil, déconnexion. Commun aux trois applications.', weight: 1)]
class MeController extends Controller
{
    /**
     * Profil de l'utilisateur connecté (spectateur ou personnel).
     */
    public function __invoke(Request $request): CustomerResource|StaffResource
    {
        $user = $request->user('sanctum');

        return match (true) {
            $user instanceof Customer => new CustomerResource($user),
            $user instanceof Staff => new StaffResource($user->load('company')),
            default => abort(403),
        };
    }
}
