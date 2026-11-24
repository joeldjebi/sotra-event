<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OtpAudience;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Auth\Concerns\IssuesAccessTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompleteRegistrationRequest;
use App\Http\Requests\Api\V1\RequestOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Models\Customer;
use App\Models\Staff;
use App\Services\OtpService;
use App\Services\RegistrationTokens;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Codes SMS (inscription, mot de passe oublié) et déconnexion des trois applications mobiles.
 * La connexion habituelle se fait par téléphone + mot de passe (PasswordController).
 */
class OtpController extends Controller
{
    use IssuesAccessTokens;

    public function __construct(private readonly OtpService $otp) {}

    /**
     * Register / login OTP — 1. Envoyer le code.
     *
     * Première étape de l'inscription ou connexion par code (`POST /auth/verify`), de l'inscription avec mot de passe
     * (`POST /auth/register`) et du mot de passe oublié (`POST /auth/password/reset`).
     * Réponse identique que le numéro soit connu ou non, pour ne pas révéler les comptes du personnel.
     *
     * @unauthenticated
     */
    #[Group(AuthDocs::OTP, AuthDocs::OTP_DESCRIPTION, weight: AuthDocs::OTP_WEIGHT)]
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
     * Register / login OTP — 2. Vérifier le code.
     *
     * Après `POST /auth/otp` (audience `customer`). Tester `data.status` :
     *
     * - `logged_in` : spectateur déjà inscrit, il est **connecté** (`token`, `user`) → ouvrir l'accueil.
     * - `registration_required` : nouveau numéro (ou compte sans nom), **aucun compte n'est encore créé** ni connecté.
     *   Garder `registration_token` (valable `registration_expires_in` secondes, une seule fois) → ouvrir l'écran
     *   « Mes informations », puis `POST /auth/register/complete`.
     *
     * Réservé aux spectateurs : le personnel se connecte avec son mot de passe (`POST /auth/login`),
     * 403 `PASSWORD_LOGIN_REQUIRED` sinon.
     *
     * @unauthenticated
     */
    #[Group(AuthDocs::OTP, AuthDocs::OTP_DESCRIPTION, weight: AuthDocs::OTP_WEIGHT)]
    public function verify(VerifyOtpRequest $request, RegistrationTokens $registrations): JsonResponse
    {
        $phone = $request->phone();

        // Vérifié avant le code, pour ne pas le consommer.
        if ($request->audience() !== OtpAudience::Customer) {
            throw new ApiException('Le personnel se connecte avec son numéro et son mot de passe.', 'PASSWORD_LOGIN_REQUIRED', 403);
        }

        $this->otp->verify($phone, OtpAudience::Customer, $request->string('code')->toString());

        $customer = Customer::query()->where('phone', $phone->e164)->first();

        if ($customer !== null && $customer->hasCompletedProfile()) {
            return $this->customerToken($customer, $request->string('device_name')->toString());
        }

        return response()->json([
            'data' => [
                /** `registration_required` : numéro vérifié, compte à créer avec `POST /auth/register/complete`. */
                'status' => 'registration_required',
                /** Jeton d'inscription, à renvoyer à `POST /auth/register/complete`. Ce n'est pas un jeton de connexion. */
                'registration_token' => $registrations->issue($phone),
                /** Validité du jeton d'inscription en secondes : au-delà, recommencer à `POST /auth/otp`. */
                'registration_expires_in' => RegistrationTokens::TTL_MINUTES * 60,
                /** Numéro vérifié, au format international. */
                'phone' => $phone->e164,
            ],
        ]);
    }

    /**
     * Register / login OTP — 3. Enregistrer mes informations (nouveau spectateur).
     *
     * Après `POST /auth/verify` qui a répondu `registration_required`. Crée le compte avec le prénom, le nom et
     * l'e-mail facultatif, puis **connecte automatiquement** le spectateur : même réponse qu'une connexion
     * (`status: logged_in`, `token`, `user`). Jeton d'inscription inconnu, déjà utilisé ou expiré :
     * 422 `REGISTRATION_EXPIRED`, recommencer à `POST /auth/otp`.
     *
     * @unauthenticated
     */
    #[Group(AuthDocs::OTP, AuthDocs::OTP_DESCRIPTION, weight: AuthDocs::OTP_WEIGHT)]
    public function completeRegistration(CompleteRegistrationRequest $request, RegistrationTokens $registrations): JsonResponse
    {
        $phone = $registrations->consume($request->string('registration_token')->toString());

        if ($phone === null) {
            throw new ApiException('Votre inscription a expiré. Recommencez avec un nouveau code.', 'REGISTRATION_EXPIRED', 422);
        }

        $customer = Customer::query()->firstOrCreate(['phone' => $phone]);
        $customer->forceFill([
            'first_name' => $request->string('first_name')->trim()->toString(),
            'last_name' => $request->string('last_name')->trim()->toString(),
            'email' => $request->filled('email') ? $request->string('email')->trim()->lower()->toString() : null,
        ])->save();

        return $this->customerToken($customer, $request->string('device_name')->toString(), $customer->wasRecentlyCreated);
    }

    /**
     * Logout (révoque le jeton de l'appareil).
     */
    #[Group(AuthDocs::ACCOUNT, AuthDocs::ACCOUNT_DESCRIPTION, weight: 1)]
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if ($user instanceof Customer || $user instanceof Staff) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Déconnecté.']);
    }
}
