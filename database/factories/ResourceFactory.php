<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Resource>
 */
class ResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = \App\Models\Resource::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('RES???')),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['vehicle', 'tandem_glider', 'equipment']),
            'description' => fake()->sentence(),
            'specifications' => [],
            'is_active' => true,
        ];
    }

    public function vehicle()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'vehicle',
            'name' => 'Navette ' . fake()->numberBetween(1, 10),
            'specifications' => [
                'capacity' => 8,
                'weight_limit' => 450,
            ],
        ]);
    }

    public function tandemGlider()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'tandem_glider',
            'name' => fake()->company() . ' Tandem',
            'specifications' => [
                'max_weight' => 180,
                'wing_size' => fake()->randomFloat(1, 30, 35),
            ],
        ]);
    }
}
