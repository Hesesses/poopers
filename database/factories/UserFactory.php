<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'avatar' => null,
            'is_pro' => false,
            'pro_expires_at' => null,
            'onesignal_player_id' => null,
            'notification_settings' => null,
        ];
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pro' => true,
            'pro_expires_at' => now()->addYear(),
        ]);
    }
}
