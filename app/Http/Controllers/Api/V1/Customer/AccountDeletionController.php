<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\Customers\DeleteCustomerAccount;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Authentification', 'Connexion par code SMS, jeton par appareil, déconnexion. Commun aux trois applications.', weight: 1)]
class AccountDeletionController extends Controller
{
    /**
     * Supprimer mon compte (bouton « Supprimer mon compte » du profil, exigé par l'App Store et Google Play).
     *
     * Effet immédiat : numéro, nom, connexions de tous les appareils, codes et journaux de messages sont effacés ;
     * les ventes restent pour la comptabilité, sans lien avec la personne. Refusé (**409** `ACCOUNT_HAS_UPCOMING_PASSES`)
     * tant que le spectateur détient un Pass pour un événement à venir. Après succès, effacer le jeton et les Pass
     * stockés sur le téléphone et revenir à l'écran d'accueil. Lien web équivalent : `/suppression-compte`.
     */
    public function __invoke(Request $request, DeleteCustomerAccount $deletion): JsonResponse
    {
        $customer = $request->user('sanctum');
        abort_unless($customer instanceof Customer, 403);

        $deletion->handle($customer, 'app');

        return response()->json(['message' => 'Votre compte a été supprimé.']);
    }
}
