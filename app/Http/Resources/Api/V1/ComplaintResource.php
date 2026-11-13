<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réclamation du spectateur et suivi de son statut (SP-23).
 *
 * @mixin Complaint
 */
class ComplaintResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'pass_code' => $this->pass?->code,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'details' => $this->details,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'resolution' => $this->resolution,
            'created_at' => $this->created_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
