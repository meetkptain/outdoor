<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Instructor;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReservationAdminControllerGeneralizedTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Activity $activity;
    protected Instructor $instructor;
    protected Site $site;
    protected User $admin;
    protected Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $this->organization->users()->attach($this->admin->id, ['role' => 'admin']);

        $instructorUser = User::factory()->create();
        $this->instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $instructorUser->id,
            'activity_types' => ['paragliding'],
            'is_active' => true,
        ]);

        $this->site = Site::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'status' => 'pending',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);
    }

    public function test_can_filter_reservations_by_activity_type(): void
    {
        // Créer une autre activité
        $surfingActivity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'surfing',
            'is_active' => true,
        ]);

        Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $surfingActivity->id,
            'activity_type' => 'surfing',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson('/api/v1/admin/reservations?activity_type=paragliding');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ]);

        $data = $response->json('data') ?? [];
        $this->assertGreaterThanOrEqual(1, count($data));
        $this->assertEquals('paragliding', $data[0]['activity_type']);
    }

    public function test_can_schedule_reservation_with_instructor_id(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->postJson("/api/v1/admin/reservations/{$this->reservation->id}/schedule", [
                'scheduled_at' => now()->addDay()->format('Y-m-d'),
                'scheduled_time' => '10:00',
                'instructor_id' => $this->instructor->id,
                'site_id' => $this->site->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->reservation->refresh();
        $this->assertEquals($this->instructor->id, $this->reservation->instructor_id);
    }

    public function test_reservation_show_includes_activity_and_sessions(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson("/api/v1/admin/reservations/{$this->reservation->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'activity',
                    'activity_sessions',
                    'instructor',
                ],
            ]);
    }

    public function test_can_filter_by_activity_type_instead_of_flight_type(): void
    {
        // Test rétrocompatibilité : flight_type devrait aussi fonctionner
        $response = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson('/api/v1/admin/reservations?flight_type=paragliding');

        $response->assertStatus(200);
        // Vérifier que les résultats sont filtrés par activity_type
        $data = $response->json('data') ?? [];
        if (count($data) > 0) {
            $this->assertEquals('paragliding', $data[0]['activity_type']);
        }
    }
}

