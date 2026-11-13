<?php

namespace Database\Factories;

use App\Enums\StaffRole;
use App\Models\Company;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'role' => StaffRole::Conveyor,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '+22505'.fake()->unique()->numerify('########'),
            'employee_number' => fake()->unique()->bothify('MAT-#####'),
            'is_active' => true,
        ];
    }

    public function role(StaffRole $role): static
    {
        return $this->state(fn (array $attributes) => ['role' => $role]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
