<?php

namespace Database\Factories;

use App\Models\ReservationHistory;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReservationHistory>
 */
class ReservationHistoryFactory extends Factory
{
    protected $model = ReservationHistory::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement([
                'created',
                'status_changed',
                'scheduled',
                'assigned',
                'updated',
                'cancelled',
                'completed',
                'payment_captured',
                'refunded',
            ]),
            'old_values' => [],
            'new_values' => [],
            'notes' => fake()->optional()->sentence(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function statusChanged(string $oldStatus, string $newStatus)
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'status_changed',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $newStatus],
        ]);
    }

    public function created()
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'created',
            'old_values' => [],
            'new_values' => ['status' => 'pending'],
        ]);
    }
}
