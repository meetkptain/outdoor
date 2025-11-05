<?php

namespace Tests\Feature\Api;

use App\Models\Reservation;
use App\Models\Payment;
use App\Models\User;
use App\Models\Biplaceur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Test accès dashboard admin
     */
    public function test_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/dashboard');

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
        Reservation::factory()->count(5)->create(['status' => 'pending']);
        Reservation::factory()->count(3)->create(['status' => 'scheduled']);
        Reservation::factory()->count(2)->create(['status' => 'completed']);
        Reservation::factory()->count(1)->create(['status' => 'cancelled']);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/dashboard/stats');

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
        Reservation::factory()->count(10)->create();

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/reservations');

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
        Reservation::factory()->count(3)->create(['status' => 'pending']);
        Reservation::factory()->count(2)->create(['status' => 'scheduled']);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/reservations?status=pending');

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
            'status' => 'pending',
        ]);

        $biplaceur = Biplaceur::factory()->create();
        $site = \App\Models\Site::factory()->create();
        $tandemGlider = \App\Models\Resource::factory()->create(['type' => 'tandem_glider']);
        $vehicle = \App\Models\Resource::factory()->create(['type' => 'vehicle']);

        $this->actingAs($this->admin, 'sanctum');

        $scheduledAt = now()->addDays(7)->setTime(10, 0);

        $response = $this->putJson("/api/v1/admin/reservations/{$reservation->id}/assign", [
            'scheduled_at' => $scheduledAt->toDateTimeString(),
            'instructor_id' => $biplaceur->user_id, // assign() utilise instructor_id
            'site_id' => $site->id,
            'tandem_glider_id' => $tandemGlider->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $response->assertStatus(200);

        $reservation->refresh();
        $this->assertEquals('scheduled', $reservation->status);
        $this->assertEquals($biplaceur->user_id, $reservation->instructor_id); // assign() utilise instructor_id
        $this->assertEquals($site->id, $reservation->site_id);
    }

    /**
     * Test capture paiement (admin)
     */
    public function test_admin_can_capture_payment(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => 'scheduled',
            'payment_status' => 'authorized',
        ]);

        $payment = Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'status' => 'requires_capture',
            'amount' => 100.00,
        ]);

        $this->actingAs($this->admin, 'sanctum');

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

        $response = $this->postJson("/api/v1/admin/reservations/{$reservation->id}/capture", [
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
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson("/api/v1/admin/reservations/{$reservation->id}/complete");

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
     * Test récupération calendrier biplaceurs
     */
    public function test_admin_can_get_biplaceurs_calendar(): void
    {
        $biplaceur = Biplaceur::factory()->create();
        
        Reservation::factory()->count(3)->create([
            'biplaceur_id' => $biplaceur->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(1),
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $startDate = now()->format('Y-m-d');
        $endDate = now()->addDays(7)->format('Y-m-d');

        $response = $this->getJson("/api/v1/admin/biplaceurs/{$biplaceur->id}/calendar?start_date={$startDate}&end_date={$endDate}");

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
            'status' => 'succeeded',
            'amount' => 100.00,
            'captured_at' => now(),
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $startDate = now()->startOfMonth()->format('Y-m-d');
        $endDate = now()->endOfMonth()->format('Y-m-d');

        $response = $this->getJson("/api/v1/admin/dashboard/revenue?start_date={$startDate}&end_date={$endDate}");

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

