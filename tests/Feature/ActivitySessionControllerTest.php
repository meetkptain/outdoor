<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Activity;
use App\Models\Instructor;
use App\Models\ActivitySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivitySessionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $admin;
    protected Activity $activity;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Utiliser le driver array pour le cache dans les tests (plus rapide et pas besoin de table)
        config(['cache.default' => 'array']);
        \Illuminate\Support\Facades\Cache::flush();
        
        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->create();
        $this->organization->users()->attach($this->admin->id, ['role' => 'admin', 'permissions' => ['*']]);
        $this->admin->setCurrentOrganization($this->organization);
        session(['organization_id' => $this->organization->id]);

        $this->activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'duration_minutes' => 90,
        ]);
    }

    public function test_can_list_activity_sessions(): void
    {
        ActivitySession::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
        ]);

        $response = $this->getJson('/api/v1/activity-sessions');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'activity_id', 'scheduled_at'],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_sessions_by_activity(): void
    {
        $activity2 = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'surfing',
        ]);

        ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
        ]);

        ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $activity2->id,
        ]);

        $response = $this->getJson("/api/v1/activity-sessions/by-activity/{$this->activity->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.activity_id', $this->activity->id);
    }

    public function test_admin_can_create_activity_session(): void
    {
        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->postJson('/api/v1/activity-sessions', [
                'activity_id' => $this->activity->id,
                'scheduled_at' => now()->addDay()->toDateTimeString(),
                'instructor_id' => $instructor->id,
                'status' => 'scheduled',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'activity_id', 'instructor_id'],
            ]);

        $this->assertDatabaseHas('activity_sessions', [
            'activity_id' => $this->activity->id,
            'instructor_id' => $instructor->id,
        ]);
    }

    public function test_can_get_activity_session_details(): void
    {
        $session = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
        ]);

        $response = $this->getJson("/api/v1/activity-sessions/{$session->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'activity_id', 'scheduled_at'],
            ]);
    }

    public function test_admin_can_update_activity_session(): void
    {
        $session = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
        ]);

        $newDate = now()->addDays(2)->toDateTimeString();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->putJson("/api/v1/activity-sessions/{$session->id}", [
                'scheduled_at' => $newDate,
                'status' => 'completed',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_admin_can_delete_activity_session(): void
    {
        $session = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->deleteJson("/api/v1/activity-sessions/{$session->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('activity_sessions', ['id' => $session->id]);
    }
}
