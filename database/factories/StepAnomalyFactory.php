<?php

namespace Database\Factories;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StepAnomaly>
 */
class StepAnomalyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->date(),
            'anomaly_type' => fake()->randomElement(AnomalyType::cases()),
            'details' => ['steps' => fake()->numberBetween(0, 200000)],
            'severity' => fake()->randomElement(AnomalySeverity::cases()),
            'reviewed' => false,
        ];
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewed' => true,
        ]);
    }
}
