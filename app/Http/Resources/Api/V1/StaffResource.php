<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Staff
 */
class StaffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'staff',
            'id' => $this->id,
            'role' => $this->role->value,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'short_name' => $this->shortName(),
            'phone' => $this->phone,
            'company' => [
                'id' => $this->company_id,
                'name' => $this->company->name,
            ],
        ];
    }
}
