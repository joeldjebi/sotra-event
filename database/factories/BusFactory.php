<?php

namespace Database\Factories;

use App\Enums\BusCategory;
use App\Enums\BusStatus;
use App\Models\Bus;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bus>
 */
class BusFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => (string) fake()->unique()->numberBetween(1, 9999),
            'plate' => fake()->unique()->bothify('#### ?? ##'),
            'capacity' => 50,
            'category' => BusCategory::Standard,
            'status' => BusStatus::InService,
        ];
    }
}
