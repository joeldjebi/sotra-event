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
class OtpController extends Controller
{
    use IssuesAccessTokens;

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
    #[Group(AuthDocs::OTP, AuthDocs::OTP_DESCRIPTION, weight: 1)]
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
     * Vérifie seulement le code reçu par SMS après `POST /auth/otp` (audience `customer`) et remet le jeton.
     * Numéro inconnu : le compte est créé, vide. Numéro connu : le spectateur est connecté. `next_step` indique
     * l'écran suivant : `complete_profile` (enregistrer les informations avec `PUT /me/profile`) ou `home`.
     * Réservé aux spectateurs : le personnel se connecte avec son mot de passe (`POST /auth/login`),
     * 403 `PASSWORD_LOGIN_REQUIRED` sinon.
     *
     * @unauthenticated
     */
    #[Group(AuthDocs::OTP, AuthDocs::OTP_DESCRIPTION, weight: 1)]
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->phone();

        // Vérifié avant le code, pour ne pas le consommer.
        if ($request->audience() !== OtpAudience::Customer) {
            throw new ApiException('Le personnel se connecte avec son numéro et son mot de passe.', 'PASSWORD_LOGIN_REQUIRED', 403);
        }

        $this->otp->verify($phone, OtpAudience::Customer, $request->string('code')->toString());

        $customer = Customer::query()->firstOrCreate(['phone' => $phone->e164]);

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
