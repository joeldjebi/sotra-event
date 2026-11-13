<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Company;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'serial_number' => fake()->unique()->bothify('PDA-########'),
            'label' => 'PDA '.fake()->unique()->numberBetween(1, 999),
            'model' => 'Zebra TC21',
            'status' => DeviceStatus::Active,
        ];
    }
}
