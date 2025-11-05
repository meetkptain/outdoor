<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'activity_type' => 'paragliding',
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'duration_minutes' => 90,
            'max_participants' => 1,
            'min_participants' => 1,
            'pricing_config' => null,
            'constraints_config' => [
                'weight' => ['min' => 40, 'max' => 120],
            ],
            'metadata' => null,
            'is_active' => true,
        ];
    }

    public function surfing()
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'surfing',
            'name' => 'Cours de Surf',
            'duration_minutes' => 60,
            'constraints_config' => [
                'age' => ['min' => 8],
            ],
        ]);
    }
}
