<?php

namespace Database\Factories;

use App\Enums\EventRequestStatus;
use App\Models\EventRequest;
use App\Models\Promoter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRequest>
 */
class EventRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promoter_id' => Promoter::factory(),
            'name' => 'Concert '.fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'venue_name' => 'Palais de la Culture, Treichville',
            'starts_at' => '2026-12-20 19:00',
            'ends_at' => '2026-12-20 23:30',
            'expected_attendance' => 8000,
            'estimated_passes' => 1500,
            'desired_communes' => 'Yopougon, Abobo, Cocody',
            'status' => EventRequestStatus::Submitted,
        ];
    }
}
