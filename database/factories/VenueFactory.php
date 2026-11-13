<?php

namespace Database\Factories;

use App\Models\Venue;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Stade '.fake()->unique()->lastName(),
            'address' => fake()->streetAddress(),
            'city' => 'Abidjan',
            'location' => Point::makeGeodetic(fake()->randomFloat(5, 5.25, 5.50), fake()->randomFloat(5, -4.10, -3.90)),
        ];
    }
}
