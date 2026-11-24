<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OtpAudience;
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
Connexion des trois applications (spectateur, convoyeur, service technique) : **numéro de téléphone + mot de passe**.

- **Login** : `POST /auth/login` avec `phone`, `audience` (`customer` pour l'app spectateur, `staff` pour les convoyeurs et techniciens), `password` et `device_name`. La réponse contient le jeton (`data.token`) et l'utilisateur connecté.
- **Inscription du spectateur** : `POST /auth/otp` (code par SMS) puis `POST /auth/register` avec le code et le mot de passe choisi.
- **Mot de passe oublié** : `POST /auth/otp` puis `POST /auth/password/reset` avec le code et le nouveau mot de passe.
- **Personnel** : le mot de passe initial est remis par l'exploitation. Tant que `must_change_password` vaut `true`, ouvrir l'écran de changement (`POST /auth/password`) : les autres appels répondent 403 `PASSWORD_CHANGE_REQUIRED`.

Envoyer ensuite le jeton dans l'en-tête `Authorization: Bearer <token>` de chaque appel. `POST /auth/logout` révoque le jeton de l'appareil.
MD;

    public function __construct(private readonly OtpService $otp) {}

    /**
     * Recevoir un code par SMS (inscription, mot de passe oublié).
     *
     * Première étape de l'inscription (`POST /auth/register`) et du mot de passe oublié (`POST /auth/password/reset`).
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
     * Connexion par code SMS, sans mot de passe (ancienne méthode).
     *
     * Conservée pour les versions de l'application déjà installées : utiliser `POST /auth/login`.
     * Le compte spectateur est créé à la première connexion.
     *
     * @deprecated
     *
     * @unauthenticated
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->phone();
        $audience = $request->audience();

        $this->otp->verify($phone, $audience, $request->string('code')->toString());

        if ($audience === OtpAudience::Customer) {
            return $this->customerToken(Customer::query()->firstOrCreate(['phone' => $phone->e164]), $request->string('device_name')->toString());
        }

        return $this->staffToken($this->findStaff($phone), $request->string('device_name')->toString());
    }

    /**
     * Logout : se déconnecter (révoque le jeton utilisé).
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
