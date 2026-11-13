<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Notes seules : le Pass, l'événement, la compagnie et le spectateur sont fournis par le test ou le seeder.
 *
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'punctuality' => fake()->numberBetween(3, 5),
            'comfort' => fake()->numberBetween(3, 5),
            'courtesy' => fake()->numberBetween(3, 5),
            'driving' => fake()->numberBetween(3, 5),
            'information' => fake()->numberBetween(2, 5),
            'comment' => fake()->optional(0.4)->randomElement(['Très bien organisé.', 'Bus à l\'heure, merci !', 'Un peu d\'attente au retour.', 'Convoyeur très aimable.']),
        ];
    }
}
