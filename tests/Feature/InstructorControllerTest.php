<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Instructor;
use App\Models\Activity;
use App\Models\ActivitySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $admin;
    protected User $instructorUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->create();
        $this->organization->users()->attach($this->admin->id, ['role' => 'admin', 'permissions' => ['*']]);
        $this->admin->setCurrentOrganization($this->organization);
        session(['organization_id' => $this->organization->id]);

        $this->instructorUser = User::factory()->create();
        $this->organization->users()->attach($this->instructorUser->id, ['role' => 'instructor', 'permissions' => []]);
        $this->instructorUser->setCurrentOrganization($this->organization);
    }

    public function test_can_list_instructors_publicly(): void
    {
        Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
            'activity_types' => json_encode(['paragliding']),
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/instructors');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'activity_types', 'experience_years'],
                ],
            ]);
    }

    public function test_can_filter_instructors_by_activity_type(): void
    {
        Instructor::withoutGlobalScopes()->where('organization_id', $this->organization->id)->delete();
        
        $paraglidingInstructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
            'activity_types' => ['paragliding'], // Utiliser un tableau directement
            'is_active' => true,
        ]);

        $surfingInstructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
            'activity_types' => ['surfing'], // Utiliser un tableau directement
            'is_active' => true,
        ]);

        // Définir le contexte d'organisation pour la requête
        config(['app.current_organization' => $this->organization->id]);
        
        $response = $this->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson('/api/v1/instructors/by-activity/paragliding');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
        $this->assertTrue(
            collect($data)->contains('id', $paraglidingInstructor->id)
        );
    }

    public function test_admin_can_create_instructor(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->postJson('/api/v1/instructors', [
                'name' => 'John Instructor',
                'email' => 'john@example.com',
                'password' => 'password123',
                'activity_types' => ['paragliding', 'surfing'],
                'license_number' => 'LIC123',
                'experience_years' => 5,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'user'],
            ]);

        $this->assertDatabaseHas('instructors', [
            'license_number' => 'LIC123',
        ]);
    }

    public function test_admin_can_get_instructor_details(): void
    {
        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson("/api/v1/instructors/{$instructor->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'user', 'activity_types', 'license_number'],
            ]);
    }

    public function test_instructor_can_view_own_sessions(): void
    {
        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->instructorUser->id,
        ]);

        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
        ]);

        ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $activity->id,
            'instructor_id' => $instructor->id,
        ]);

        $response = $this->actingAs($this->instructorUser, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson('/api/v1/instructors/me/sessions');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'activity_id', 'instructor_id'],
                ],
            ]);
    }

    public function test_instructor_can_update_availability(): void
    {
        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->instructorUser->id,
        ]);

        $response = $this->actingAs($this->instructorUser, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->putJson('/api/v1/instructors/me/availability', [
                'availability' => [
                    'days' => [1, 2, 3, 4, 5],
                    'hours' => [9, 10, 11, 12, 13, 14, 15, 16, 17],
                    'exceptions' => [],
                ],
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['availability'],
            ]);
    }
}
