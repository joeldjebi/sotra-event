<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rules\Password;

/**
 * Inscription et mot de passe oublié : code SMS reçu + nouveau mot de passe.
 */
class SetPasswordRequest extends VerifyOtpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'password' => ['required', 'string', 'confirmed', self::passwordRule()],
        ]);
    }

    /**
     * Mot de passe des applications mobiles : 8 caractères au moins, avec des lettres et des chiffres.
     */
    public static function passwordRule(): Password
    {
        return Password::min(8)->letters()->numbers()->max(100);
    }
}
