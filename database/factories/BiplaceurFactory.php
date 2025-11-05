<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Biplaceur>
 */
class BiplaceurFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = \App\Models\Biplaceur::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'license_number' => fake()->unique()->bothify('BP####'),
            'certifications' => ['brevet_biplaceur', 'first_aid'],
            'experience_years' => fake()->numberBetween(2, 20),
            'total_flights' => fake()->numberBetween(50, 5000),
            'max_flights_per_day' => fake()->numberBetween(3, 8),
            'availability' => [
                'monday' => ['09:00', '18:00'],
                'tuesday' => ['09:00', '18:00'],
                'wednesday' => ['09:00', '18:00'],
                'thursday' => ['09:00', '18:00'],
                'friday' => ['09:00', '18:00'],
                'saturday' => ['08:00', '19:00'],
                'sunday' => ['08:00', '19:00'],
            ],
            'is_active' => true,
            'can_tap_to_pay' => fake()->boolean(50),
        ];
    }
}
