<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyLine;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyLine>
 */
class CompanyLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'commune_code' => 'YOP',
            'place' => 'Gare '.fake()->lastName(),
            'location' => Point::makeGeodetic(5.34, -4.08),
            'bus_counts' => ['standard' => 4, 'premium' => 1, 'gold' => 0],
        ];
    }
}
