<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Instructor;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $admin;
    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->organization->users()->attach($this->admin->id, [
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        $this->site = Site::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_cannot_assign_instructor_without_required_activity(): void
    {
        $surfActivity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'surfing',
        ]);

        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $surfActivity->id,
            'activity_type' => 'surfing',
            'participants_count' => 1,
        ]);

        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_types' => ['paragliding'], // ne peut pas enseigner le surf
            'availability' => [
                'days' => [1, 2, 3, 4, 5, 6, 7],
                'hours' => range(7, 18),
                'exceptions' => [],
            ],
        ]);

        $scheduledAt = now()->addDay()->setTime(9, 0)->toIso8601String();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->putJson("/api/v1/admin/reservations/{$reservation->id}/assign", [
                'scheduled_at' => $scheduledAt,
                'instructor_id' => $instructor->id,
                'site_id' => $this->site->id,
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('n\'est pas qualifié', $response->json('message'));
    }

    public function test_enforces_max_sessions_per_day_for_instructor(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
        ]);

        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_types' => ['paragliding'],
            'max_sessions_per_day' => 1,
            'availability' => [
                'days' => [1, 2, 3, 4, 5, 6, 7],
                'hours' => range(7, 18),
                'exceptions' => [],
            ],
        ]);

        $primaryReservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $activity->id,
            'activity_type' => 'paragliding',
            'participants_count' => 1,
        ]);

        $secondReservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $activity->id,
            'activity_type' => 'paragliding',
            'participants_count' => 1,
        ]);

        $scheduledAt = now()->addDays(2)->setTime(10, 0)->toIso8601String();

        $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->putJson("/api/v1/admin/reservations/{$primaryReservation->id}/assign", [
                'scheduled_at' => $scheduledAt,
                'instructor_id' => $instructor->id,
                'site_id' => $this->site->id,
            ])
            ->assertStatus(200);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->putJson("/api/v1/admin/reservations/{$secondReservation->id}/assign", [
                'scheduled_at' => $scheduledAt,
                'instructor_id' => $instructor->id,
                'site_id' => $this->site->id,
            ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('Limite de sessions atteinte', $response->json('message'));
    }
}


