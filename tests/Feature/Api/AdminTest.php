<?php

namespace Tests\Feature\Api;

use App\Models\Reservation;
use App\Models\Payment;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Utiliser le driver array pour le cache dans les tests (plus rapide et pas besoin de table)
        config(['cache.default' => 'array']);
        \Illuminate\Support\Facades\Cache::flush();
        
        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->organization->users()->attach($this->admin->id, ['role' => 'admin', 'permissions' => ['*']]);
        $this->admin->setCurrentOrganization($this->organization);
    }

    /**
     * Test accès dashboard admin
     */
    public function test_admin_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'stats',
                ],
            ]);
    }

    /**
     * Test récupération statistiques réservations
     */
    public function test_admin_can_get_reservation_stats(): void
    {
        // Créer des réservations avec différents statuts
        Reservation::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'status' => 'pending'
        ]);
        Reservation::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'status' => 'scheduled'
        ]);
        Reservation::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'status' => 'completed'
        ]);
        Reservation::factory()->count(1)->create([
            'organization_id' => $this->organization->id,
            'status' => 'cancelled'
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson('/api/v1/admin/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reservations' => [
                        'pending',
                        'scheduled',
                        'completed',
                        'cancelled',
                    ],
                ],
            ]);
    }

    /**
     * Test récupération liste réservations (admin)
     */
    public function test_admin_can_list_reservations(): void
    {
        Reservation::factory()->count(10)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson('/api/v1/admin/reservations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'uuid',
                            'customer_email',
                            'status',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Test filtrage réservations par statut
     */
    public function test_admin_can_filter_reservations_by_status(): void
    {
        Reservation::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'status' => 'pending'
        ]);
        Reservation::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'status' => 'scheduled'
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson('/api/v1/admin/reservations?status=pending');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        
        foreach ($data as $reservation) {
            $this->assertEquals('pending', $reservation['status']);
        }
    }

    /**
     * Test assignation ressources à une réservation (admin)
     */
    public function test_admin_can_assign_resources_to_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'pending',
        ]);

        $instructor = \App\Models\Instructor::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $site = \App\Models\Site::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $tandemGlider = \App\Models\Resource::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'tandem_glider'
        ]);
        $vehicle = \App\Models\Resource::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'vehicle'
        ]);

        $scheduledAt = now()->addDays(7)->setTime(10, 0);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->putJson("/api/v1/admin/reservations/{$reservation->id}/assign", [
                'scheduled_at' => $scheduledAt->toDateTimeString(),
                'instructor_id' => $instructor->id,
                'site_id' => $site->id,
                'equipment_id' => $tandemGlider->id,
                'vehicle_id' => $vehicle->id,
            ]);

        $response->assertStatus(200);

        $reservation->refresh();
        $this->assertEquals('scheduled', $reservation->status);
        $this->assertEquals($instructor->id, $reservation->instructor_id);
        $this->assertEquals($site->id, $reservation->site_id);
    }

    /**
     * Test capture paiement (admin)
     */
    public function test_admin_can_capture_payment(): void
    {
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'scheduled',
            'payment_status' => 'authorized',
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $this->organization->id,
            'reservation_id' => $reservation->id,
            'status' => 'requires_capture',
            'amount' => 100.00,
        ]);

        // Mock PaymentService pour éviter les appels Stripe réels
        $paymentServiceMock = $this->mock(\App\Services\PaymentService::class);
        $paymentServiceMock->shouldReceive('capturePayment')
            ->once()
            ->with(\Mockery::on(function ($paymentArg) use ($payment) {
                return $paymentArg->id === $payment->id;
            }), 100.00)
            ->andReturn(true); // capturePayment retourne bool

        // Injecter le mock dans le container
        $this->app->instance(\App\Services\PaymentService::class, $paymentServiceMock);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->postJson("/api/v1/admin/reservations/{$reservation->id}/capture", [
                'amount' => 100.00,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    /**
     * Test complétion réservation (admin)
     */
    public function test_admin_can_complete_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->postJson("/api/v1/admin/reservations/{$reservation->id}/complete");

        $response->assertStatus(200);

        $reservation->refresh();
        $this->assertEquals('completed', $reservation->status);
    }

    /**
     * Test non-admin ne peut pas accéder dashboard
     */
    public function test_non_admin_cannot_access_dashboard(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum');

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(403); // Forbidden
    }

    /**
     * Test récupération calendrier biplaceurs (deprecated - utiliser instructors)
     */
    public function test_admin_can_get_biplaceurs_calendar(): void
    {
        $instructor = \App\Models\Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_types' => ['paragliding'],
        ]);
        
        $activity = \App\Models\Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
        ]);

        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $activity->id,
            'instructor_id' => $instructor->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(1),
        ]);

        $startDate = now()->format('Y-m-d');
        $endDate = now()->addDays(7)->format('Y-m-d');

        // Utiliser la route instructors au lieu de biplaceurs
        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson("/api/v1/instructors/{$instructor->id}/calendar?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * Test récupération revenus
     */
    public function test_admin_can_get_revenue_stats(): void
    {
        // Créer des paiements réussis
        Payment::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'status' => 'succeeded',
            'amount' => 100.00,
            'captured_at' => now(),
        ]);

        $startDate = now()->startOfMonth()->format('Y-m-d');
        $endDate = now()->endOfMonth()->format('Y-m-d');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson("/api/v1/admin/dashboard/revenue?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'start_date',
                    'end_date',
                    'total_revenue',
                ],
            ]);
    }
}

