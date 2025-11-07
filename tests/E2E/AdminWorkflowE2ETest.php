<?php

namespace Tests\E2E;

use App\Models\Activity;
use App\Models\Instructor;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Test E2E : Scénario complet de workflow admin
 * 
 * Ce test simule un scénario admin complet :
 * 1. Admin consulte le dashboard
 * 2. Admin liste les réservations
 * 3. Admin assigne des ressources
 * 4. Admin capture les paiements
 * 5. Admin consulte les statistiques
 */
class AdminWorkflowE2ETest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $admin;
    protected Activity $activity;
    protected Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['cache.default' => 'array']);
        Cache::flush();
        
        $this->organization = Organization::factory()->create();
        
        $this->admin = User::factory()->admin()->create();
        $this->admin->organizations()->attach($this->organization->id, ['role' => 'admin']);
        
        $this->activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
        ]);
        
        $this->reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'status' => 'pending',
        ]);
        
        // Mock PaymentService
        $this->mockPaymentService();
    }

    protected function mockPaymentService(): void
    {
        $paymentServiceMock = $this->mock(\App\Services\PaymentService::class);
        
        $paymentServiceMock->shouldReceive('capturePayment')
            ->andReturnUsing(function ($payment, $amount = null) {
                $payment->update([
                    'status' => 'captured',
                    'captured_at' => now(),
                ]);
                return $payment;
            });
        
        $this->app->instance(\App\Services\PaymentService::class, $paymentServiceMock);
    }

    public function test_complete_admin_workflow(): void
    {
        // ========== ÉTAPE 1 : Admin consulte le dashboard ==========
        $dashboardResponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/admin/dashboard');

        $dashboardResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        // ========== ÉTAPE 2 : Admin liste les réservations ==========
        $reservationsResponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/admin/reservations?status=pending');

        $reservationsResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ]);

        $this->assertGreaterThan(0, count($reservationsResponse->json('data')));

        // ========== ÉTAPE 3 : Admin consulte une réservation ==========
        $reservationResponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson("/api/v1/admin/reservations/{$this->reservation->id}");

        $reservationResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'status',
                    'customer_email',
                ],
            ]);

        // ========== ÉTAPE 4 : Admin assigne des ressources ==========
        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
        ]);
        
        $site = Site::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        
        $equipment = Resource::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'tandem_glider',
        ]);

        $scheduledAt = now()->addDays(7)->setTime(10, 0);

        $assignResponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->putJson("/api/v1/admin/reservations/{$this->reservation->id}/assign", [
                'scheduled_at' => $scheduledAt->toDateTimeString(),
                'instructor_id' => $instructor->id,
                'site_id' => $site->id,
                'equipment_id' => $equipment->id,
            ]);

        $assignResponse->assertStatus(200);

        $this->reservation->refresh();
        $this->assertEquals('scheduled', $this->reservation->status);
        $this->assertNotNull($this->reservation->scheduled_at);

        // ========== ÉTAPE 5 : Admin consulte les statistiques ==========
        $statsResponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/admin/dashboard/stats?period=month');

        $statsResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        // ========== ÉTAPE 6 : Admin consulte le résumé ==========
        $summaryResponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/admin/dashboard/summary?period=month');

        $summaryResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        // ========== ÉTAPE 7 : Admin filtre les réservations ==========
        $filteredResponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson("/api/v1/admin/reservations?status=scheduled&activity_type=paragliding");

        $filteredResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ]);
    }
}

