<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use App\Rules\ValidPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayOrderRequest extends FormRequest
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
        $isMobileMoney = PaymentMethod::tryFrom((string) $this->input('method'))?->isMobileMoney() ?? false;

        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'msisdn' => [Rule::requiredIf($isMobileMoney), 'nullable', 'string', new ValidPhoneNumber],
        ];
    }
}
