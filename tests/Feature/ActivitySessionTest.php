<?php

namespace Tests\Feature;

use App\Models\ActivitySession;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\Instructor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivitySessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_activity_session(): void
    {
        $organization = Organization::factory()->create();
        $activity = Activity::factory()->create(['organization_id' => $organization->id]);

        $session = ActivitySession::create([
            'organization_id' => $organization->id,
            'activity_id' => $activity->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseHas('activity_sessions', [
            'activity_id' => $activity->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_activity_session_belongs_to_activity(): void
    {
        $organization = Organization::factory()->create();
        $activity = Activity::factory()->create(['organization_id' => $organization->id]);
        $session = ActivitySession::factory()->create([
            'organization_id' => $organization->id,
            'activity_id' => $activity->id,
        ]);

        $this->assertEquals($activity->id, $session->activity->id);
    }

    public function test_activity_session_can_be_assigned_to_instructor(): void
    {
        $organization = Organization::factory()->create();
        $activity = Activity::factory()->create(['organization_id' => $organization->id]);
        $instructor = Instructor::factory()->create(['organization_id' => $organization->id]);

        $session = ActivitySession::factory()->create([
            'organization_id' => $organization->id,
            'activity_id' => $activity->id,
            'instructor_id' => $instructor->id,
        ]);

        $this->assertEquals($instructor->id, $session->instructor->id);
    }

    public function test_activity_session_status_scopes(): void
    {
        $organization = Organization::factory()->create();
        $activity = Activity::factory()->create(['organization_id' => $organization->id]);

        ActivitySession::factory()->create([
            'organization_id' => $organization->id,
            'activity_id' => $activity->id,
            'status' => 'scheduled',
        ]);

        ActivitySession::factory()->completed()->create([
            'organization_id' => $organization->id,
            'activity_id' => $activity->id,
        ]);

        $this->assertCount(1, ActivitySession::scheduled()->get());
        $this->assertCount(1, ActivitySession::completed()->get());
    }
}
