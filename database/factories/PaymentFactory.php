<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = \App\Models\Payment::class;

    public function definition(): array
    {
        return [
            'reservation_id' => \App\Models\Reservation::factory(),
            'stripe_payment_intent_id' => 'pi_' . fake()->bothify('????????????????????????'),
            'type' => fake()->randomElement(['deposit', 'authorization', 'capture']),
            'amount' => fake()->randomFloat(2, 50, 500),
            'currency' => 'EUR',
            'status' => fake()->randomElement(['pending', 'requires_capture', 'succeeded', 'failed']),
            'payment_method_type' => 'card',
        ];
    }

    public function requiresCapture()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'requires_capture',
        ]);
    }

    public function succeeded()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
            'captured_at' => now(),
        ]);
    }
}
