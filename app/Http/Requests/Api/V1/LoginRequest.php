<?php

namespace App\Http\Requests\Api\V1;

class LoginRequest extends RequestOtpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'password' => ['required', 'string', 'max:100'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
    }
}
