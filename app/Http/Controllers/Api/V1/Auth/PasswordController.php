<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OtpAudience;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Auth\Concerns\IssuesAccessTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\SetPasswordRequest;
use App\Models\Customer;
use App\Models\Staff;
use App\Services\OtpService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Connexion des applications mobiles par téléphone + mot de passe ; inscription et mot de passe oublié par code SMS.
 */
#[Group(OtpController::DOCS_GROUP, OtpController::DOCS_GROUP_DESCRIPTION, weight: 1)]
class PasswordController extends Controller
{
    use IssuesAccessTokens;

    public function __construct(private readonly OtpService $otp) {}

    /**
     * Login : se connecter avec son téléphone et son mot de passe.
     *
     * Même réponse que le numéro soit inconnu ou le mot de passe faux (401 `INVALID_CREDENTIALS`).
     * Un compte créé avant les mots de passe n'en a pas encore : passer par « Mot de passe oublié ».
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $phone = $request->phone();
        $password = $request->string('password')->toString();
        $account = $request->audience() === OtpAudience::Customer
            ? Customer::query()->where('phone', $phone->e164)->whereNull('anonymized_at')->first()
            : $this->findStaff($phone);

        // Hachage fait même sans compte, pour un temps de réponse identique.
        $hash = $account->password ?? Hash::make('compte-inexistant');

        if (! Hash::check($password, $hash) || $account?->password === null) {
            throw new ApiException('Numéro ou mot de passe incorrect.', 'INVALID_CREDENTIALS', 401);
        }

        return $account instanceof Customer
            ? $this->customerToken($account, $request->string('device_name')->toString())
            : $this->staffToken($account, $request->string('device_name')->toString());
    }

    /**
     * Inscription du spectateur : valider le code SMS et choisir son mot de passe.
     *
     * Après `POST /auth/otp` (audience `customer`). Le spectateur est connecté dans la foulée.
     * Un numéro qui a déjà un mot de passe reçoit 409 `ACCOUNT_EXISTS` : proposer la connexion ou « Mot de passe oublié ».
     *
     * @unauthenticated
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $phone = $request->phone();
        $this->otp->verify($phone, OtpAudience::Customer, $request->string('code')->toString());

        $customer = Customer::query()->firstOrCreate(['phone' => $phone->e164]);

        if ($customer->password !== null) {
            throw new ApiException('Ce numéro a déjà un compte. Connectez-vous ou utilisez « Mot de passe oublié ».', 'ACCOUNT_EXISTS', 409);
        }

        $customer->forceFill(array_filter([
            'password' => $request->string('password')->toString(),
            'first_name' => $request->filled('first_name') ? $request->string('first_name')->trim()->toString() : null,
            'last_name' => $request->filled('last_name') ? $request->string('last_name')->trim()->toString() : null,
        ], fn (?string $value): bool => $value !== null))->save();

        return $this->customerToken($customer, $request->string('device_name')->toString(), $customer->wasRecentlyCreated);
    }

    /**
     * Mot de passe oublié : valider le code SMS et choisir un nouveau mot de passe.
     *
     * Après `POST /auth/otp` avec la même `audience`. Les autres appareils sont déconnectés et l'utilisateur
     * est connecté sur celui-ci. Sert aussi au premier mot de passe d'un compte créé avant les mots de passe.
     *
     * @unauthenticated
     */
    public function reset(SetPasswordRequest $request): JsonResponse
    {
        $phone = $request->phone();
        $this->otp->verify($phone, $request->audience(), $request->string('code')->toString());
        $password = $request->string('password')->toString();
        $deviceName = $request->string('device_name')->toString();

        if ($request->audience() === OtpAudience::Customer) {
            $customer = Customer::query()->firstOrCreate(['phone' => $phone->e164]);

            DB::transaction(function () use ($customer, $password): void {
                $customer->tokens()->delete();
                $customer->forceFill(['password' => $password])->save();
            });

            return $this->customerToken($customer, $deviceName);
        }

        $staff = $this->findStaff($phone);

        if ($staff !== null && $staff->canSignIn()) {
            DB::transaction(function () use ($staff, $password): void {
                $staff->tokens()->delete();
                $staff->forceFill(['password' => $password, 'must_change_password' => false])->save();
            });
        }

        return $this->staffToken($staff, $deviceName);
    }

    /**
     * Changer son mot de passe (connecté).
     *
     * Obligatoire pour le personnel à la première connexion (`must_change_password`). Les autres appareils sont
     * déconnectés ; celui-ci garde son jeton.
     */
    public function change(ChangePasswordRequest $request): JsonResponse
    {
        $account = $request->user('sanctum');
        abort_unless($account instanceof Customer || $account instanceof Staff, 403);

        if ($account->password === null || ! Hash::check($request->string('current_password')->toString(), $account->password)) {
            throw new ApiException('Le mot de passe actuel est incorrect.', 'INVALID_CURRENT_PASSWORD', 422);
        }

        $currentTokenId = $account->currentAccessToken()->getKey();

        DB::transaction(function () use ($account, $request, $currentTokenId): void {
            $account->tokens()->whereKeyNot($currentTokenId)->delete();
            $account->forceFill(array_merge(
                ['password' => $request->string('password')->toString()],
                $account instanceof Staff ? ['must_change_password' => false] : [],
            ))->save();
        });

        return response()->json(['message' => 'Mot de passe modifié.']);
    }
}
