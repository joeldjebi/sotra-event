<?php

namespace App\Http\Controllers\Api\V1\Auth\Concerns;

use App\Enums\TokenAbility;
use App\Exceptions\ApiException;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Resources\Api\V1\StaffResource;
use App\Models\Customer;
use App\Models\Staff;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;

/**
 * Jeton d'accès remis à l'appareil après une connexion réussie (mot de passe ou code SMS).
 */
trait IssuesAccessTokens
{
    protected function findStaff(PhoneNumber $phone): ?Staff
    {
        return Staff::query()->withoutGlobalScopes()->where('phone', $phone->e164)->first();
    }

    protected function customerToken(Customer $customer, string $deviceName, bool $isNewAccount = false): JsonResponse
    {
        $customer->forceFill(['last_login_at' => now()])->save();
        $abilities = [TokenAbility::Customer->value];

        return $this->tokenResponse($customer->createToken($deviceName, $abilities)->plainTextToken, $abilities, new CustomerResource($customer), false, $isNewAccount);
    }

    protected function staffToken(?Staff $staff, string $deviceName): JsonResponse
    {
        $ability = $staff?->role->tokenAbility();

        if ($staff === null || ! $staff->canSignIn() || $ability === null) {
            throw new ApiException('Ce compte n\'est pas autorisé à se connecter.', 'ACCOUNT_NOT_ALLOWED', 403);
        }

        $staff->forceFill(['last_login_at' => now()])->saveQuietly();

        return $this->tokenResponse(
            $staff->createToken($deviceName, [$ability->value])->plainTextToken,
            [$ability->value],
            new StaffResource($staff->load('company')),
            $staff->must_change_password,
            false,
        );
    }

    /**
     * @param  list<string>  $abilities
     */
    private function tokenResponse(string $token, array $abilities, CustomerResource|StaffResource $user, bool $mustChangePassword, bool $isNewAccount): JsonResponse
    {
        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'abilities' => $abilities,
                'is_new_account' => $isNewAccount,
                'must_change_password' => $mustChangePassword,
                'user' => $user,
            ],
        ]);
    }
}
