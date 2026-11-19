<?php

namespace Database\Factories;

use App\Enums\CallReason;
use App\Models\CallLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallLog>
 */
class CallLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '+22507'.fake()->numerify('########'),
            'reason' => CallReason::Information,
            'notes' => fake()->sentence(),
        ];
    }
}
