<?php

namespace Database\Factories;

use App\Enums\Offer;
use App\Enums\OrderStatus;
use App\Enums\PassType;
use App\Enums\SalesChannel;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Les commandes réalistes se créent par l'action CreateOrder ; cette factory sert aux cas limites des tests.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'CMD-'.Str::upper(Str::random(8)),
            'offer' => Offer::Standard,
            'pass_type' => PassType::RoundTrip,
            'quantity' => 1,
            'unit_price' => 3000,
            'subtotal' => 3000,
            'fee_rate' => 2,
            'fee_amount' => 60,
            'commission_rate' => 10,
            'commission_amount' => 300,
            'company_amount' => 2700,
            'total' => 3060,
            'channel' => SalesChannel::Web,
            'status' => OrderStatus::PendingPayment,
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
