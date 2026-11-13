<?php

namespace Database\Factories;

use App\Enums\TechTeamStatus;
use App\Enums\TechTeamType;
use App\Models\Company;
use App\Models\TechTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechTeam>
 */
class TechTeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => 'T'.fake()->unique()->numberBetween(1, 999),
            'type' => TechTeamType::Light,
            'status' => TechTeamStatus::Available,
        ];
    }
}
