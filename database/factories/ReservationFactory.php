<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = \App\Models\Reservation::class;

    public function definition(): array
    {
        return [
            'uuid' => \Illuminate\Support\Str::uuid(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_first_name' => fake()->firstName(),
            'customer_last_name' => fake()->lastName(),
            'customer_birth_date' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'customer_weight' => fake()->numberBetween(50, 120),
            'customer_height' => fake()->numberBetween(150, 200),
            'flight_type' => fake()->randomElement(['tandem', 'initiation', 'perfectionnement']),
            'participants_count' => fake()->numberBetween(1, 2),
            'status' => 'pending',
            'base_amount' => fake()->randomFloat(2, 100, 500),
            'options_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => fake()->randomFloat(2, 100, 500),
            'deposit_amount' => fake()->randomFloat(2, 30, 150),
            'payment_status' => 'pending',
        ];
    }
}
