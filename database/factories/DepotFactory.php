<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Depot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Depot>
 */
class DepotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Dépôt de '.fake()->unique()->city(),
            'address' => fake()->streetAddress(),
            'phone' => '+22527'.fake()->numerify('########'),
        ];
    }
}
