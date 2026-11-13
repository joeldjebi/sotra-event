<?php

namespace Database\Factories;

use App\Models\Promoter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promoter>
 */
class PromoterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'email' => fake()->companyEmail(),
            'phone' => '+22507'.fake()->numerify('########'),
            'is_active' => true,
        ];
    }
}
