<?php

namespace App\Http\Requests\Api\V1;

class VerifyOtpRequest extends RequestOtpRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'code' => ['required', 'string', 'digits:'.config('eventtrans.otp.length')],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
    }
}
