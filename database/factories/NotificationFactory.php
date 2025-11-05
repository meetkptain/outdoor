<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['email', 'sms', 'push']),
            'template' => fake()->randomElement(['reservation-confirmation', 'reminder', 'assignment', 'thank-you']),
            'recipient' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'content' => fake()->paragraph(),
            'status' => fake()->randomElement(['pending', 'sent', 'failed']),
            'sent_at' => fake()->boolean(70) ? now() : null,
            'metadata' => [],
        ];
    }

    public function sent()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function email()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'email',
        ]);
    }

    public function sms()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sms',
        ]);
    }
}
