<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' FC',
            'attack' => fake()->numberBetween(50, 85),
            'midfield' => fake()->numberBetween(50, 85),
            'defence' => fake()->numberBetween(50, 85),
            'fitness' => fake()->numberBetween(60, 90),
        ];
    }
}
