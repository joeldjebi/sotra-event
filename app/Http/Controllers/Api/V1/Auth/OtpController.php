<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OtpAudience;
use App\Enums\TokenAbility;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Resources\Api\V1\StaffResource;
use App\Models\Customer;
use App\Models\Staff;
use App\Services\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Connexion par téléphone + code OTP : spectateurs (app), convoyeurs et techniciens (§ 2 du CDC).
 *
 * @tags Authentification
 */
class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * Demander un code de connexion par SMS.
     *
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
     * Vérifier le code et obtenir un jeton d'accès.
     *
     * Le compte spectateur est créé à la première connexion.
     *
     * @unauthenticated
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->phone();
        $audience = $request->audience();

        $this->otp->verify($phone, $audience, $request->string('code')->toString());

        if ($audience === OtpAudience::Customer) {
            $customer = Customer::query()->firstOrCreate(['phone' => $phone->e164]);
            $customer->forceFill(['last_login_at' => now()])->save();

            return $this->tokenResponse(
                $customer->createToken($request->string('device_name')->toString(), [TokenAbility::Customer->value])->plainTextToken,
                [TokenAbility::Customer->value],
                new CustomerResource($customer),
            );
        }

        $staff = $this->findStaff($phone);
        $ability = $staff?->role->tokenAbility();

        if ($staff === null || ! $staff->canSignIn() || $ability === null) {
            throw new ApiException('Ce compte n\'est pas autorisé à se connecter.', 'ACCOUNT_NOT_ALLOWED', 403);
        }

        $staff->forceFill(['last_login_at' => now()])->saveQuietly();

        return $this->tokenResponse(
            $staff->createToken($request->string('device_name')->toString(), [$ability->value])->plainTextToken,
            [$ability->value],
            new StaffResource($staff->load('company')),
        );
    }

    /**
     * Se déconnecter (révoque le jeton utilisé).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if ($user instanceof Customer || $user instanceof Staff) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Déconnecté.']);
    }

    private function findStaff(PhoneNumber $phone): ?Staff
    {
        return Staff::query()->withoutGlobalScopes()->where('phone', $phone->e164)->first();
    }

    /**
     * @param  list<string>  $abilities
     */
    private function tokenResponse(string $token, array $abilities, CustomerResource|StaffResource $user): JsonResponse
    {
        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'abilities' => $abilities,
                'user' => $user,
            ],
        ]);
    }
}
