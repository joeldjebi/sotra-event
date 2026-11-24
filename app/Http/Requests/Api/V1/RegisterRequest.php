<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OtpAudience;

/**
 * Inscription d'un spectateur : code SMS reçu (audience `customer`) + mot de passe choisi.
 */
class RegisterRequest extends SetPasswordRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(array_diff_key(parent::rules(), ['audience' => true]), [
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
        ]);
    }

    public function audience(): OtpAudience
    {
        return OtpAudience::Customer;
    }
}
