<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'phone' => $this->faker->phoneNumber(),
            'weight' => $this->faker->numberBetween(50, 120),
            'height' => $this->faker->numberBetween(150, 200),
            'medical_notes' => $this->faker->optional()->sentence(),
            'notes' => $this->faker->optional()->paragraph(),
            'total_flights' => $this->faker->numberBetween(0, 50),
            'total_spent' => $this->faker->randomFloat(2, 0, 5000),
            'last_flight_date' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the client is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the client has no flights.
     */
    public function noFlights(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_flights' => 0,
            'total_spent' => 0,
            'last_flight_date' => null,
        ]);
    }
}

