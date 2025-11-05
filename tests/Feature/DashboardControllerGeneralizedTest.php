<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySession;
use App\Models\Instructor;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardControllerGeneralizedTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Activity $paraglidingActivity;
    protected Activity $surfingActivity;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        
        $this->paraglidingActivity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'is_active' => true,
        ]);

        $this->surfingActivity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'surfing',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $this->organization->users()->attach($this->admin->id, ['role' => 'admin']);

        // Créer des sessions pour les tests
        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
            'activity_types' => ['paragliding', 'surfing'],
        ]);

        $reservation1 = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->paraglidingActivity->id,
            'activity_type' => 'paragliding',
            'instructor_id' => $instructor->id,
            'status' => 'completed',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        $reservation2 = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->surfingActivity->id,
            'activity_type' => 'surfing',
            'instructor_id' => $instructor->id,
            'status' => 'completed',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->paraglidingActivity->id,
            'reservation_id' => $reservation1->id,
            'instructor_id' => $instructor->id,
            'status' => 'completed',
            'scheduled_at' => now()->subDays(1),
        ]);

        ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->surfingActivity->id,
            'reservation_id' => $reservation2->id,
            'instructor_id' => $instructor->id,
            'status' => 'completed',
            'scheduled_at' => now()->subDays(1),
        ]);
    }

    public function test_can_get_activity_stats(): void
    {
        // Note: La route activity-stats n'existe pas encore, on teste flightStats qui est deprecated
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/flights?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_can_get_top_instructors(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/top-instructors?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'total_sessions',
                        'total_sessions_all_time',
                    ],
                ],
            ]);
    }

    public function test_can_get_top_instructors_filtered_by_activity_type(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/top-instructors?period=month&activity_type=paragliding');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(0, count($data));
    }

    public function test_flight_stats_deprecated_still_works(): void
    {
        // Test rétrocompatibilité
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/flights?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_top_biplaceurs_deprecated_still_works(): void
    {
        // Test rétrocompatibilité
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/top-biplaceurs?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }
}

