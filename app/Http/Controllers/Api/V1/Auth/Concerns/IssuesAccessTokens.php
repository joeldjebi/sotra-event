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

        return $this->tokenResponse(
            $customer->createToken($deviceName, $abilities)->plainTextToken,
            $abilities,
            new CustomerResource($customer),
            mustChangePassword: false,
            isNewAccount: $isNewAccount,
            profileCompleted: $customer->hasCompletedProfile(),
            nextStep: $customer->hasCompletedProfile() ? 'home' : 'complete_profile',
        );
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
            mustChangePassword: $staff->must_change_password,
            isNewAccount: false,
            profileCompleted: true,
            nextStep: $staff->must_change_password ? 'change_password' : 'home',
        );
    }

    /**
     * @param  list<string>  $abilities
     */
    private function tokenResponse(
        string $token,
        array $abilities,
        CustomerResource|StaffResource $user,
        bool $mustChangePassword,
        bool $isNewAccount,
        bool $profileCompleted,
        string $nextStep,
    ): JsonResponse {
        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'abilities' => $abilities,
                'next_step' => $nextStep,
                'is_new_account' => $isNewAccount,
                'profile_completed' => $profileCompleted,
                'must_change_password' => $mustChangePassword,
                'user' => $user,
            ],
        ]);
    }
}
