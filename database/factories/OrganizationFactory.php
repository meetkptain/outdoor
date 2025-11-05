<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = \App\Models\Organization::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(),
            'name' => fake()->company(),
            'domain' => fake()->optional()->domainName(),
            'logo_url' => fake()->optional()->imageUrl(),
            'primary_color' => fake()->hexColor(),
            'secondary_color' => fake()->hexColor(),
            'settings' => [],
            'features' => [],
            'subscription_tier' => 'free',
            'subscription_status' => 'active',
            'metadata' => [],
        ];
    }

    public function pro()
    {
        return $this->state(fn (array $attributes) => [
            'subscription_tier' => 'pro',
        ]);
    }

    public function withFeatures(array $features)
    {
        return $this->state(fn (array $attributes) => [
            'features' => $features,
        ]);
    }
}
