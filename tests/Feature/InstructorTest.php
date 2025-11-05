<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\Organization;
use App\Models\User;
use App\Models\ActivitySession;
use App\Models\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_instructor(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $instructor = Instructor::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'activity_types' => ['paragliding'],
            'license_number' => 'LIC1234',
            'max_sessions_per_day' => 5,
        ]);

        $this->assertDatabaseHas('instructors', [
            'user_id' => $user->id,
            'license_number' => 'LIC1234',
        ]);
    }

    public function test_instructor_can_teach_multiple_activities(): void
    {
        $organization = Organization::factory()->create();
        $instructor = Instructor::factory()->create([
            'organization_id' => $organization->id,
            'activity_types' => ['paragliding', 'surfing'],
        ]);

        $this->assertTrue($instructor->canTeachActivity('paragliding'));
        $this->assertTrue($instructor->canTeachActivity('surfing'));
        $this->assertFalse($instructor->canTeachActivity('diving'));
    }

    public function test_instructor_can_add_activity_type(): void
    {
        $organization = Organization::factory()->create();
        $instructor = Instructor::factory()->create([
            'organization_id' => $organization->id,
            'activity_types' => ['paragliding'],
        ]);

        $instructor->addActivityType('surfing');

        $instructor->refresh();
        $this->assertTrue($instructor->canTeachActivity('surfing'));
        $this->assertTrue($instructor->canTeachActivity('paragliding'));
    }

    public function test_instructor_can_remove_activity_type(): void
    {
        $organization = Organization::factory()->create();
        $instructor = Instructor::factory()->create([
            'organization_id' => $organization->id,
            'activity_types' => ['paragliding', 'surfing'],
        ]);

        $instructor->removeActivityType('surfing');

        $instructor->refresh();
        $this->assertFalse($instructor->canTeachActivity('surfing'));
        $this->assertTrue($instructor->canTeachActivity('paragliding'));
    }

    public function test_instructor_availability_check(): void
    {
        $organization = Organization::factory()->create();
        $instructor = Instructor::factory()->create([
            'organization_id' => $organization->id,
            'availability' => [
                'days' => [1, 2, 3], // Lundi, Mardi, Mercredi
                'hours' => [9, 10, 11],
            ],
        ]);

        // Lundi prochain
        $monday = now()->next(1);
        $this->assertTrue($instructor->isAvailableOn($monday->format('Y-m-d')));

        // Dimanche prochain
        $sunday = now()->next(0);
        $this->assertFalse($instructor->isAvailableOn($sunday->format('Y-m-d')));
    }

    public function test_instructor_scope_for_activity_type(): void
    {
        $organization = Organization::factory()->create();

        Instructor::factory()->create([
            'organization_id' => $organization->id,
            'activity_types' => ['paragliding'],
        ]);

        Instructor::factory()->create([
            'organization_id' => $organization->id,
            'activity_types' => ['surfing'],
        ]);

        $paraglidingInstructors = Instructor::forActivityType('paragliding')->get();
        $this->assertCount(1, $paraglidingInstructors);
    }
}
