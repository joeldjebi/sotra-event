<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Point d'entrée après connexion : envoie chaque utilisateur vers son espace.
 */
class SpaceRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if ($user->mustEnrollTwoFactor()) {
            return redirect()->route('security.two-factor');
        }

        $space = $user->space();
        abort_if($space === null, 403, 'Aucun rôle ne vous est attribué.');

        return redirect()->route($space->homeRoute());
    }
}
