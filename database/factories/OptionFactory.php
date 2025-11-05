<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Option>
 */
class OptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = \App\Models\Option::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('OPT???')),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(['photo', 'video', 'souvenir', 'insurance', 'transport', 'other']),
            'price' => fake()->randomFloat(2, 10, 100),
            'price_per_participant' => fake()->boolean(30) ? fake()->randomFloat(2, 5, 50) : null,
            'is_active' => true,
            'is_upsellable' => fake()->boolean(70),
            'max_quantity' => fake()->numberBetween(1, 5),
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
