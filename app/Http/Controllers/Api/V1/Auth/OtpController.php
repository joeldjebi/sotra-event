<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OtpAudience;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Auth\Concerns\IssuesAccessTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Models\Customer;
use App\Models\Staff;
use App\Services\OtpService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Codes SMS (inscription, mot de passe oublié) et déconnexion des trois applications mobiles.
 * La connexion habituelle se fait par téléphone + mot de passe (PasswordController).
 */
#[Group(self::DOCS_GROUP, self::DOCS_GROUP_DESCRIPTION, weight: 1)]
class OtpController extends Controller
{
    use IssuesAccessTokens;

    /** Groupe de la documentation de l'API, partagé avec le profil et la suppression du compte. */
    public const DOCS_GROUP = 'Connexion (login)';

    public const DOCS_GROUP_DESCRIPTION = <<<'MD'
Connexion des trois applications (spectateur, convoyeur, service technique).

- **Spectateur, par code SMS (inscription ou connexion, sans mot de passe)** : `POST /auth/otp` avec `phone` et `audience: customer`, puis `POST /auth/verify` avec `phone`, `audience`, `code` et `device_name`. Le compte est créé s'il n'existe pas (`is_new_account: true`), sinon le spectateur est connecté.
- **Spectateur ou personnel, par mot de passe** : `POST /auth/login` avec `phone`, `audience` (`customer` ou `staff`), `password` et `device_name`. Inscription avec mot de passe : `POST /auth/otp` puis `POST /auth/register`. Mot de passe oublié : `POST /auth/otp` puis `POST /auth/password/reset`.
- **Personnel** : le mot de passe initial est remis par l'exploitation. Tant que `must_change_password` vaut `true`, ouvrir l'écran de changement (`POST /auth/password`) : les autres appels répondent 403 `PASSWORD_CHANGE_REQUIRED`.

La réponse contient le jeton (`data.token`) et l'utilisateur connecté. Envoyer ensuite le jeton dans l'en-tête `Authorization: Bearer <token>` de chaque appel. `POST /auth/logout` révoque le jeton de l'appareil.
MD;

    public function __construct(private readonly OtpService $otp) {}

    /**
     * Register / login OTP — 1. Envoyer le code SMS.
     *
     * Première étape de l'inscription ou connexion par code (`POST /auth/verify`), de l'inscription avec mot de passe
     * (`POST /auth/register`) et du mot de passe oublié (`POST /auth/password/reset`).
     * Réponse identique que le numéro soit connu ou non, pour ne pas révéler les comptes du personnel.
     *
     * @unauthenticated
     */
    public function send(RequestOtpRequest $request): JsonResponse
    {
        $phone = $request->phone();
        $audience = $request->audience();

        if ($audience === OtpAudience::Customer || $this->findStaff($phone)?->canSignIn()) {
            $this->otp->send($phone, $audience, $request->ip());
        }

        return response()->json([
            'message' => 'Si ce numéro est autorisé, un code vient d\'être envoyé par SMS.',
            'data' => [
                'expires_in' => (int) config('eventtrans.otp.ttl_minutes') * 60,
                'resend_in' => (int) config('eventtrans.otp.resend_cooldown_seconds'),
            ],
        ], 202);
    }

    /**
     * Register / login OTP — 2. Valider le code (spectateur).
     *
     * Après `POST /auth/otp` (audience `customer`). Numéro inconnu : le compte est créé (`is_new_account: true`,
     * afficher l'écran de profil) ; numéro connu : le spectateur est connecté. `first_name` et `last_name` sont
     * facultatifs et ne remplacent pas un nom déjà enregistré. Réservé aux spectateurs : le personnel se connecte
     * avec son mot de passe (`POST /auth/login`), 403 `PASSWORD_LOGIN_REQUIRED` sinon.
     *
     * @unauthenticated
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->phone();

        // Vérifié avant le code, pour ne pas le consommer.
        if ($request->audience() !== OtpAudience::Customer) {
            throw new ApiException('Le personnel se connecte avec son numéro et son mot de passe.', 'PASSWORD_LOGIN_REQUIRED', 403);
        }

        $this->otp->verify($phone, OtpAudience::Customer, $request->string('code')->toString());

        $customer = Customer::query()->firstOrCreate(['phone' => $phone->e164]);

        foreach (['first_name', 'last_name'] as $field) {
            if ($customer->{$field} === null && $request->filled($field)) {
                $customer->{$field} = $request->string($field)->trim()->toString();
            }
        }

        return $this->customerToken($customer, $request->string('device_name')->toString(), $customer->wasRecentlyCreated);
    }

    /**
     * Logout (révoque le jeton de l'appareil).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if ($user instanceof Customer || $user instanceof Staff) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Déconnecté.']);
    }
}
