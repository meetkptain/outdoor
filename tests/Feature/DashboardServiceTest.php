<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySession;
use App\Models\Instructor;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DashboardService $service;
    protected Organization $organization;
    protected Activity $activity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DashboardService();
        $this->organization = Organization::factory()->create();
        $this->activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'is_active' => true,
        ]);
    }

    public function test_can_get_top_instructors(): void
    {
        $instructor1 = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $instructor2 = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
        ]);

        // Créer des sessions complétées pour instructor1
        for ($i = 0; $i < 5; $i++) {
            $reservation = Reservation::factory()->create([
                'organization_id' => $this->organization->id,
                'activity_id' => $this->activity->id,
                'activity_type' => 'paragliding',
                'status' => 'completed',
                'base_amount' => 100,
                'options_amount' => 0,
                'total_amount' => 100,
                'deposit_amount' => 50,
            ]);

            ActivitySession::factory()->create([
                'organization_id' => $this->organization->id,
                'activity_id' => $this->activity->id,
                'reservation_id' => $reservation->id,
                'instructor_id' => $instructor1->id,
                'status' => 'completed',
                'scheduled_at' => now()->subDays($i),
            ]);
        }

        // Créer des sessions complétées pour instructor2
        for ($i = 0; $i < 3; $i++) {
            $reservation = Reservation::factory()->create([
                'organization_id' => $this->organization->id,
                'activity_id' => $this->activity->id,
                'activity_type' => 'paragliding',
                'status' => 'completed',
                'base_amount' => 100,
                'options_amount' => 0,
                'total_amount' => 100,
                'deposit_amount' => 50,
            ]);

            ActivitySession::factory()->create([
                'organization_id' => $this->organization->id,
                'activity_id' => $this->activity->id,
                'reservation_id' => $reservation->id,
                'instructor_id' => $instructor2->id,
                'status' => 'completed',
                'scheduled_at' => now()->subDays($i),
            ]);
        }

        $topInstructors = $this->service->getTopInstructors(10, 'month');

        $this->assertGreaterThanOrEqual(2, $topInstructors->count());
        $this->assertEquals($instructor1->id, $topInstructors->first()['id']);
        $this->assertEquals(5, $topInstructors->first()['total_sessions']);
    }

    public function test_can_get_top_instructors_filtered_by_activity_type(): void
    {
        $surfingActivity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'surfing',
            'is_active' => true,
        ]);

        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
        ]);

        // Créer des sessions paragliding
        for ($i = 0; $i < 3; $i++) {
            $reservation = Reservation::factory()->create([
                'organization_id' => $this->organization->id,
                'activity_id' => $this->activity->id,
                'activity_type' => 'paragliding',
                'status' => 'completed',
                'base_amount' => 100,
                'options_amount' => 0,
                'total_amount' => 100,
                'deposit_amount' => 50,
            ]);

            ActivitySession::factory()->create([
                'organization_id' => $this->organization->id,
                'activity_id' => $this->activity->id,
                'reservation_id' => $reservation->id,
                'instructor_id' => $instructor->id,
                'status' => 'completed',
                'scheduled_at' => now()->subDays($i),
            ]);
        }

        // Créer des sessions surfing
        for ($i = 0; $i < 5; $i++) {
            $reservation = Reservation::factory()->create([
                'organization_id' => $this->organization->id,
                'activity_id' => $surfingActivity->id,
                'activity_type' => 'surfing',
                'status' => 'completed',
                'base_amount' => 100,
                'options_amount' => 0,
                'total_amount' => 100,
                'deposit_amount' => 50,
            ]);

            ActivitySession::factory()->create([
                'organization_id' => $this->organization->id,
                'activity_id' => $surfingActivity->id,
                'reservation_id' => $reservation->id,
                'instructor_id' => $instructor->id,
                'status' => 'completed',
                'scheduled_at' => now()->subDays($i),
            ]);
        }

        $topInstructors = $this->service->getTopInstructors(10, 'month', 'surfing');

        $this->assertGreaterThanOrEqual(1, $topInstructors->count());
        $this->assertEquals(5, $topInstructors->first()['total_sessions']);
    }

    public function test_can_get_activity_stats(): void
    {
        // Créer des sessions
        for ($i = 0; $i < 5; $i++) {
            $reservation = Reservation::factory()->create([
                'organization_id' => $this->organization->id,
                'activity_id' => $this->activity->id,
                'activity_type' => 'paragliding',
                'status' => 'completed',
                'base_amount' => 100,
                'options_amount' => 0,
                'total_amount' => 100,
                'deposit_amount' => 50,
            ]);

            ActivitySession::factory()->create([
                'organization_id' => $this->organization->id,
                'activity_id' => $this->activity->id,
                'reservation_id' => $reservation->id,
                'status' => 'completed',
                'scheduled_at' => now()->subDays($i),
            ]);
        }

        $stats = $this->service->getActivityStats('month', 'paragliding');

        $this->assertEquals(5, $stats['total_sessions']);
        $this->assertEquals('paragliding', $stats['activity_type']);
        $this->assertEquals(5, $stats['by_status']['completed']);
        $this->assertEquals(100.0, $stats['completion_rate']);
    }

    public function test_get_top_biplaceurs_is_deprecated_but_works(): void
    {
        // Test de rétrocompatibilité
        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'status' => 'completed',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation->id,
            'instructor_id' => $instructor->id,
            'status' => 'completed',
            'scheduled_at' => now(),
        ]);

        $topBiplaceurs = $this->service->getTopBiplaceurs(10, 'month');

        // La méthode deprecated devrait fonctionner et retourner les instructeurs paragliding
        $this->assertGreaterThanOrEqual(1, $topBiplaceurs->count());
    }
}

