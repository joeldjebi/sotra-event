<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Offer;
use App\Enums\PassType;
use App\Rules\ValidPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
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
            'event_id' => ['required', 'integer'],
            'boarding_point_id' => ['required', 'integer'],
            'offer' => ['required', Rule::enum(Offer::class)],
            'pass_type' => ['required', Rule::enum(PassType::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('eventtrans.sales.max_passes_per_order')],
            'beneficiary_phone' => ['nullable', 'string', new ValidPhoneNumber],
            // NF-14 : CGV et politique de confidentialité acceptées à l'achat (pas pour un simple devis).
            'accept_terms' => $this->routeIs('api.v1.orders.store') ? ['required', 'accepted'] : ['exclude'],
        ];
    }
}
