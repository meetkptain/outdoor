<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'stripe_subscription_id' => 'sub_' . fake()->unique()->regexify('[A-Za-z0-9]{24}'),
            'stripe_price_id' => 'price_' . fake()->regexify('[A-Za-z0-9]{24}'),
            'tier' => fake()->randomElement(['free', 'starter', 'pro', 'enterprise']),
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'features' => [],
            'metadata' => null,
        ];
    }

    public function active()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function cancelled()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'canceled_at' => now(),
        ]);
    }

    public function forTier(string $tier)
    {
        return $this->state(fn (array $attributes) => [
            'tier' => $tier,
        ]);
    }
}
