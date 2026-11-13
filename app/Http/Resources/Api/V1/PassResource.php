<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\DropZoneUsage;
use App\Enums\PassStatus;
use App\Models\Pass;
use App\Services\Finance\RefundPolicy;
use App\Services\Quality\ReviewService;
use App\Services\Sales\PassNotifier;
use App\Services\Sales\PassTokenService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pass du spectateur (SP-14 à SP-17). qr_token est stockable sur le téléphone pour l'affichage hors ligne.
 *
 * @mixin Pass
 */
class PassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $point = $this->boardingPoint;
        $pickup = $this->event->dropZones->firstWhere('usage', DropZoneUsage::PickUp);

        return [
            'code' => $this->code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'offer' => $this->offer->value,
            'offer_label' => $this->offer->label(),
            'pass_type' => $this->pass_type->value,
            'pass_type_label' => $this->pass_type->label(),
            'qr_token' => $this->status === PassStatus::Valid || $this->status === PassStatus::OutboundUsed || $this->status === PassStatus::ReturnUsed
                ? app(PassTokenService::class)->sign($this->resource) : null,
            'public_url' => $this->code ? PassNotifier::publicUrl($this->resource) : null,
            'transferable' => $this->isTransferable(),
            'cancellation' => $this->cancellation(),
            'refund' => $this->refund ? [
                'reference' => $this->refund->reference,
                'amount' => $this->refund->amount,
                'retained' => $this->refund->retained_amount,
                'status' => $this->refund->status->value,
                'status_label' => $this->refund->status->label(),
            ] : null,
            'review' => [
                'sent' => $this->review()->exists(),
                'available' => app(ReviewService::class)->canReview($this->resource),
            ],
            'event' => [
                'id' => $this->event->id,
                'name' => $this->event->name,
                'starts_at' => $this->event->starts_at->toIso8601String(),
                'venue' => $this->event->venue->name,
            ],
            'outbound' => $this->includesOutbound() ? [
                'commune' => $point->commune,
                'place' => $point->place,
                'boarding_call_at' => $point->boardingCallAt()->toIso8601String(),
                'departure_at' => $point->departure_at->toIso8601String(),
                'scanned' => in_array($this->status, [PassStatus::OutboundUsed, PassStatus::Completed], true),
            ] : null,
            'return' => $this->includesReturn() ? [
                'departure_at' => $point->return_departure_at->toIso8601String(),
                'pickup_zone' => $pickup?->name,
                'scanned' => in_array($this->status, [PassStatus::ReturnUsed, PassStatus::Completed], true),
            ] : null,
        ];
    }

    /**
     * Annulation possible (SP-25) : montants et date limite selon les règles RG-18 et RG-19.
     *
     * @return array<string, mixed>
     */
    private function cancellation(): array
    {
        $quote = app(RefundPolicy::class)->quote($this->resource);

        return [
            'allowed' => $quote['allowed'],
            'reason' => $quote['reason']?->value,
            'paid' => $quote['paid'],
            'retained' => $quote['retained'],
            'refund_amount' => $quote['amount'],
            'deadline' => $quote['deadline']?->toIso8601String(),
            'message' => $quote['message'],
        ];
    }
}
