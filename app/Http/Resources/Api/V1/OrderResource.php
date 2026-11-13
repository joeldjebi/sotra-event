<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'event' => ['id' => $this->event_id, 'name' => $this->event->name],
            'boarding_point' => ['id' => $this->boarding_point_id, 'commune' => $this->boardingPoint->commune, 'place' => $this->boardingPoint->place],
            'offer' => $this->offer->value,
            'pass_type' => $this->pass_type->value,
            'quantity' => $this->quantity,
            'beneficiary_phone' => $this->beneficiary_phone,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,
            'fee_rate' => (float) $this->fee_rate,
            'fee_amount' => $this->fee_amount,
            'total' => $this->total,
            'expires_at' => $this->expires_at->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'passes' => $this->when($this->status->value === 'payee', fn () => $this->passes->pluck('code')->all()),
        ];
    }
}
