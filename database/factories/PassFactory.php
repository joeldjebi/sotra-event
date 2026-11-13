<?php

namespace Database\Factories;

use App\Enums\Offer;
use App\Enums\PassStatus;
use App\Enums\PassType;
use App\Models\Pass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pass>
 */
class PassFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer' => Offer::Standard,
            'pass_type' => PassType::RoundTrip,
            'status' => PassStatus::Valid,
            'price' => 3000,
            'fee_amount' => 60,
            'commission_amount' => 300,
            'company_amount' => 2700,
        ];
    }
}
