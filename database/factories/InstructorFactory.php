<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstructorFactory extends Factory
{
    protected $model = Instructor::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'activity_types' => json_encode(['paragliding']),
            'license_number' => fake()->unique()->numerify('LIC####'),
            'certifications' => json_encode(['DEJEPS', 'UFOLEP']),
            'experience_years' => fake()->numberBetween(1, 20),
            'availability' => json_encode([
                'days' => [1, 2, 3, 4, 5], // Lundi à Vendredi
                'hours' => [9, 10, 11, 12, 13, 14, 15, 16, 17],
            ]),
            'max_sessions_per_day' => 5,
            'can_accept_instant_bookings' => false,
            'is_active' => true,
            'metadata' => json_encode([]),
        ];
    }

    public function withMultipleActivities()
    {
        return $this->state(fn (array $attributes) => [
            'activity_types' => json_encode(['paragliding', 'surfing']),
        ]);
    }

    public function canAcceptInstantBookings()
    {
        return $this->state(fn (array $attributes) => [
            'can_accept_instant_bookings' => true,
        ]);
    }
}
