<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Activity;
use App\Models\ActivitySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->create();
        $this->organization->users()->attach($this->admin->id, ['role' => 'admin', 'permissions' => ['*']]);
        $this->admin->setCurrentOrganization($this->organization);
        session(['organization_id' => $this->organization->id]);
    }

    public function test_can_list_activities_publicly(): void
    {
        Activity::withoutGlobalScopes()->where('organization_id', $this->organization->id)->delete();
        
        Activity::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        // Définir le contexte d'organisation via header et session
        $response = $this->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson('/api/v1/activities');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'activity_type', 'duration_minutes'],
                ],
            ]);
        
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_filter_activities_by_type(): void
    {
        Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'is_active' => true,
        ]);

        Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'surfing',
            'is_active' => true,
        ]);

        $response = $this->withSession(['organization_id' => $this->organization->id])
            ->getJson('/api/v1/activities/by-type/paragliding');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.activity_type', 'paragliding');
    }

    public function test_admin_can_create_activity(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->postJson('/api/v1/activities', [
                'activity_type' => 'surfing',
                'name' => 'Cours de Surf',
                'description' => 'Cours de surf pour débutants',
                'duration_minutes' => 60,
                'max_participants' => 8,
                'min_participants' => 1,
                'pricing_config' => ['base_price' => 50],
                'constraints_config' => ['age' => ['min' => 8]],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'activity_type'],
            ]);

        $this->assertDatabaseHas('activities', [
            'activity_type' => 'surfing',
            'name' => 'Cours de Surf',
        ]);
    }

    public function test_can_get_activity_details(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->withSession(['organization_id' => $this->organization->id])
            ->getJson("/api/v1/activities/{$activity->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name', 'activity_type', 'duration_minutes'],
            ]);
    }

    public function test_can_get_activity_sessions(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        ActivitySession::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $activity->id,
        ]);

        $response = $this->withSession(['organization_id' => $this->organization->id])
            ->getJson("/api/v1/activities/{$activity->id}/sessions");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_update_activity(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->putJson("/api/v1/activities/{$activity->id}", [
                'name' => 'Activité Modifiée',
                'duration_minutes' => 90,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Activité Modifiée')
            ->assertJsonPath('data.duration_minutes', 90);
    }

    public function test_admin_can_delete_activity(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->deleteJson("/api/v1/activities/{$activity->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('activities', ['id' => $activity->id]);
    }
}
