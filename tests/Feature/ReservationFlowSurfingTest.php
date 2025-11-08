<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySession;
use App\Models\Instructor;
use App\Models\Option;
use App\Models\Organization;
use App\Models\Resource;
use App\Models\Reservation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationFlowSurfingTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Activity $activity;
    protected User $admin;
    protected Instructor $instructor;
    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'surfing',
            'name' => 'Cours de Surf',
            'duration_minutes' => 60,
            'min_participants' => 1,
            'max_participants' => 6,
            'pricing_config' => [
                'model' => 'per_participant',
                'price_per_participant' => 80,
            ],
            'constraints_config' => [
                'required_metadata' => ['swimming_level'],
            ],
        ]);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->organization->users()->attach($this->admin->id, [
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        $this->instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_types' => ['surfing'],
            'max_sessions_per_day' => 6,
            'availability' => [
                'days' => [1, 2, 3, 4, 5, 6, 7],
                'hours' => range(7, 18),
                'exceptions' => [],
            ],
        ]);

        $this->site = Site::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_complete_surfing_reservation_flow(): void
    {
        // Mock PaymentService pour éviter les appels Stripe réels
        $paymentServiceMock = $this->mock(\App\Services\PaymentService::class);
        $paymentServiceMock->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn(\App\Models\Payment::factory()->make([
                'status' => 'requires_capture',
                'stripe_data' => ['client_secret' => 'pi_test_secret'],
            ]));

        $paymentServiceMock->shouldReceive('capturePayment')
            ->zeroOrMoreTimes()
            ->andReturn(true);

        // Créer une option générique (ex: location de combinaison)
        $option = Option::factory()->create([
            'organization_id' => $this->organization->id,
            'price' => 25.00,
        ]);

        // 1. Création de la réservation côté client
        $reservationData = [
            'customer_email' => 'client-surf@example.com',
            'customer_first_name' => 'Alice',
            'customer_last_name' => 'Wave',
            'activity_id' => $this->activity->id,
            'participants_count' => 2,
            'metadata' => [
                'swimming_level' => 'advanced',
            ],
            'payment_type' => 'deposit',
            'payment_method_id' => 'pm_test_surf',
        ];

        $response = $this->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->postJson('/api/v1/reservations', $reservationData);

        $response->assertStatus(201);

        $reservation = Reservation::where('customer_email', 'client-surf@example.com')->first();
        $this->assertNotNull($reservation);
        $this->assertEquals('surfing', $reservation->activity_type);
        $this->assertEquals('pending', $reservation->status);

        // 2. Côté admin : ajout d'une option (location de combinaison)
        $optionsResponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->postJson("/api/v1/admin/reservations/{$reservation->id}/add-options", [
                'options' => [
                    ['id' => $option->id, 'quantity' => 2],
                ],
                'stage' => 'before_flight',
            ]);

        $optionsResponse->assertStatus(200, $optionsResponse->getContent());

        $reservation->refresh();
        $this->assertGreaterThan(0, (float) $reservation->options_amount);

        // 3. Planification de la session
        $scheduledAt = now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s');

        $scheduleResponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->postJson("/api/v1/admin/reservations/{$reservation->id}/schedule", [
                'scheduled_at' => $scheduledAt,
                'scheduled_time' => '09:00',
                'instructor_id' => $this->instructor->id,
                'site_id' => $this->site->id,
            ]);

        $scheduleResponse->assertStatus(200, $scheduleResponse->getContent());

        $reservation->refresh();
        $this->assertEquals('scheduled', $reservation->status);
        $this->assertEquals($this->instructor->id, $reservation->instructor_id);

        $session = $reservation->activitySessions()->first();
        $this->assertNotNull($session);
        $this->assertEquals('scheduled', $session->status);

        // 4. Compléter la réservation (fin de session)
        $this->actingAs($this->admin, 'sanctum')
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->postJson("/api/v1/admin/reservations/{$reservation->id}/complete")
            ->assertStatus(200);

        $reservation->refresh();
        $this->assertEquals('completed', $reservation->status);
        $session->refresh();
        $this->assertEquals('completed', $session->status);

        // 5. Vérifications finales
        $this->assertGreaterThan(0, (float) $reservation->base_amount);
        $this->assertGreaterThan(0, (float) $reservation->options_amount);
        $this->assertTrue($reservation->history()->where('action', 'created')->exists());
        $this->assertTrue($reservation->history()->whereIn('action', ['assigned', 'scheduled'])->exists());
        $this->assertTrue($reservation->history()->where('action', 'options_added')->exists());
        $this->assertTrue($reservation->history()->where('action', 'completed')->exists());
    }
}


