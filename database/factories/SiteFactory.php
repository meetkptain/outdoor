<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = \App\Models\Site::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('SITE???')),
            'name' => fake()->words(3, true) . ' Site',
            'description' => fake()->sentence(),
            'location' => fake()->city() . ', ' . fake()->country(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'altitude' => fake()->numberBetween(500, 2000),
            'difficulty_level' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
            'orientation' => fake()->randomElement(['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW', 'multi']),
            'wind_conditions' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
