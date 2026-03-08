<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<League>
 */
class LeagueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' League',
            'icon' => '💩',
            'timezone' => 'UTC',
            'invite_code' => League::generateInviteCode(),
            'created_by' => User::factory(),
            'is_pro_league' => false,
        ];
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pro_league' => true,
        ]);
    }
}
