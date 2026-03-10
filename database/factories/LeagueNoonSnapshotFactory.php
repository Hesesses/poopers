<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\LeagueNoonSnapshot>
 */
class LeagueNoonSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'league_id' => League::factory(),
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'steps' => fake()->numberBetween(1000, 15000),
            'modified_steps' => fake()->numberBetween(1000, 15000),
            'position' => 1,
        ];
    }
}
