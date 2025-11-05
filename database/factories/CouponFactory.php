<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = \App\Models\Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('COUPON???')),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'discount_type' => fake()->randomElement(['percentage', 'fixed']),
            'discount_value' => fake()->numberBetween(5, 50),
            'min_purchase_amount' => fake()->randomFloat(2, 0, 100),
            'max_discount' => fake()->randomFloat(2, 0, 100),
            'valid_from' => now()->subDays(30),
            'valid_until' => now()->addDays(90),
            'usage_limit' => fake()->numberBetween(10, 1000),
            'usage_count' => 0,
            'is_active' => true,
            'applicable_flight_types' => ['tandem', 'initiation', 'perfectionnement'],
        ];
    }
}
