<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OtpAudience;
use App\Rules\ValidPhoneNumber;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new ValidPhoneNumber],
            'audience' => ['required', Rule::enum(OtpAudience::class)],
        ];
    }

    public function phone(): PhoneNumber
    {
        return PhoneNumber::parse($this->string('phone')->toString());
    }

    public function audience(): OtpAudience
    {
        return $this->enum('audience', OtpAudience::class) ?? OtpAudience::Customer;
    }
}
