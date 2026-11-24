<?php

namespace App\Http\Requests\Api\V1;

/**
 * Création du compte d'un nouveau spectateur après la vérification de son code SMS.
 */
class CompleteRegistrationRequest extends UpdateProfileRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'registration_token' => ['required', 'string', 'size:64'],
        ], parent::rules(), [
            'device_name' => ['required', 'string', 'max:100'],
        ]);
    }
}
