<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Company;
use App\Models\Event;
use App\Models\Promoter;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Concert '.fake()->unique()->lastName();
        $startsAt = now()->addDays(20)->setTime(18, 0);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'venue_id' => Venue::factory(),
            'promoter_id' => Promoter::factory(),
            'company_id' => Company::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->setTime(23, 0),
            'estimated_passes' => 3000,
            'commission_rate' => 10,
            'fee_rate' => 2,
            'channels' => ['web', 'app'],
            'status' => EventStatus::Draft,
        ];
    }

    public function status(EventStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
