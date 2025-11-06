<?php

namespace Tests\Feature;

use App\Models\Biplaceur;
use App\Models\Option;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test flux complet de réservation
     * 1. Création réservation
     * 2. Assignation ressources
     * 3. Capture paiement
     * 4. Complétion vol
     */
    public function test_complete_reservation_flow(): void
    {
        // Mock PaymentService pour éviter les appels Stripe réels
        $paymentServiceMock = $this->mock(\App\Services\PaymentService::class);
        $paymentServiceMock->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn(\App\Models\Payment::factory()->make([
                'status' => 'requires_capture',
                'stripe_data' => ['client_secret' => 'pi_test_secret'],
            ]));
        $this->app->instance(\App\Services\PaymentService::class, $paymentServiceMock);

        // Créer une organisation et une activité
        $organization = \App\Models\Organization::factory()->create();
        $activity = \App\Models\Activity::factory()->create([
            'organization_id' => $organization->id,
            'activity_type' => 'paragliding',
        ]);

        // 1. Créer une réservation
        $reservationData = [
            'customer_email' => 'client@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'customer_weight' => 75,
            'customer_height' => 175,
            'activity_id' => $activity->id,
            'participants_count' => 1,
            'payment_type' => 'deposit',
            'payment_method_id' => 'pm_test_1234567890', // Requis pour CreateReservationRequest
        ];

        $response = $this->postJson('/api/v1/reservations', $reservationData);
        $response->assertStatus(201);

        $reservation = Reservation::where('customer_email', 'client@example.com')->first();
        $this->assertEquals('pending', $reservation->status);

        // 2. Assigner des ressources (admin)
        $admin = User::factory()->create(['role' => 'admin']);
        $organization->users()->attach($admin->id, ['role' => 'admin', 'permissions' => ['*']]);
        $instructor = \App\Models\Instructor::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $site = Site::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $tandemGlider = Resource::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'tandem_glider',
        ]);
        $vehicle = Resource::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'vehicle',
        ]);

        $scheduledAt = now()->addDays(7)->setTime(10, 0);

        $assignResponse = $this->actingAs($admin, 'sanctum')
            ->withSession(['organization_id' => $organization->id])
            ->withHeaders(['X-Organization-ID' => $organization->id])
            ->putJson("/api/v1/admin/reservations/{$reservation->id}/assign", [
                'scheduled_at' => $scheduledAt->toDateTimeString(),
                'instructor_id' => $instructor->id,
                'site_id' => $site->id,
                'equipment_id' => $tandemGlider->id,
                'vehicle_id' => $vehicle->id,
            ]);

        $assignResponse->assertStatus(200);

        $reservation->refresh();
        $this->assertEquals('scheduled', $reservation->status);
        $this->assertNotNull($reservation->scheduled_at);

        // 3. Capturer le paiement (admin)
        $payment = $reservation->payments()->first();
        if ($payment && method_exists($payment, 'canBeCaptured') && $payment->canBeCaptured()) {
            // Mock PaymentService pour éviter les appels Stripe réels
            $paymentServiceMock = $this->mock(\App\Services\PaymentService::class);
            $paymentServiceMock->shouldReceive('capturePayment')
                ->once()
                ->andReturn(true);
            $this->app->instance(\App\Services\PaymentService::class, $paymentServiceMock);

            $captureResponse = $this->actingAs($admin, 'sanctum')
                ->withSession(['organization_id' => $organization->id])
                ->withHeaders(['X-Organization-ID' => $organization->id])
                ->postJson("/api/v1/admin/reservations/{$reservation->id}/capture", [
                    'amount' => $payment->amount ?? 100.00,
                ]);

            $captureResponse->assertStatus(200);
        }

        // 4. Marquer comme complété (admin)
        $completeResponse = $this->actingAs($admin, 'sanctum')
            ->withSession(['organization_id' => $organization->id])
            ->withHeaders(['X-Organization-ID' => $organization->id])
            ->postJson("/api/v1/admin/reservations/{$reservation->id}/complete");

        $completeResponse->assertStatus(200);

        $reservation->refresh();
        $this->assertEquals('completed', $reservation->status);
    }

    /**
     * Test ajout d'options après création réservation
     */
    public function test_can_add_options_after_reservation_creation(): void
    {
        // Créer une organisation et une activité
        $organization = \App\Models\Organization::factory()->create();
        $activity = \App\Models\Activity::factory()->create([
            'organization_id' => $organization->id,
            'activity_type' => 'paragliding',
        ]);
        
        // Mock PaymentService pour éviter les appels Stripe réels
        // createAdditionalPayment peut ne pas être appelé si le montant n'augmente pas
        $paymentServiceMock = $this->mock(\App\Services\PaymentService::class);
        $paymentServiceMock->shouldReceive('createAdditionalPayment')
            ->zeroOrMoreTimes()
            ->andReturn(\App\Models\Payment::factory()->make());
        $this->app->instance(\App\Services\PaymentService::class, $paymentServiceMock);

        $reservation = Reservation::factory()->create([
            'status' => 'pending',
            'organization_id' => $organization->id,
            'activity_id' => $activity->id,
            'activity_type' => $activity->activity_type,
            'base_amount' => 100.00,
            'options_amount' => 0.00,
            'total_amount' => 100.00,
        ]);

        $option = Option::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'price' => 25.00,
        ]);

        // Définir le contexte d'organisation pour que GlobalTenantScope fonctionne
        config(['app.current_organization' => $organization->id]);

        $response = $this->postJson("/api/v1/reservations/{$reservation->uuid}/add-options", [
            'options' => [
                ['id' => $option->id, 'quantity' => 1],
            ],
            'payment_method_id' => 'pm_test_1234567890', // Requis
        ]);

        $response->assertStatus(200);

        $this->assertTrue($reservation->fresh()->options()->where('options.id', $option->id)->exists());
    }

    /**
     * Test validation contraintes biplaceur lors assignation
     */
    public function test_validates_biplaceur_constraints_on_assignment(): void
    {
        // Créer une organisation
        $organization = \App\Models\Organization::factory()->create();
        
        // Créer un biplaceur avec limite de 5 vols/jour
        $biplaceur = Biplaceur::factory()->create([
            'organization_id' => $organization->id,
            'max_flights_per_day' => 5,
        ]);

        // Créer 5 réservations déjà assignées pour ce biplaceur aujourd'hui
        $scheduledAt = now()->setTime(10, 0);
        for ($i = 0; $i < 5; $i++) {
            Reservation::factory()->create([
                'organization_id' => $organization->id,
                'biplaceur_id' => $biplaceur->id,
                'status' => 'scheduled',
                'scheduled_at' => $scheduledAt->copy()->addHours($i),
            ]);
        }

        // Tenter d'assigner une 6ème réservation
        $newReservation = Reservation::factory()->create([
            'status' => 'pending',
            'organization_id' => $organization->id,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $organization->users()->attach($admin->id, ['role' => 'admin', 'permissions' => ['*']]);
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $tandemGlider = Resource::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'tandem_glider'
        ]);
        $vehicle = Resource::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'vehicle'
        ]);

        // Utiliser schedule() au lieu de assign() pour tester les contraintes biplaceur
        $response = $this->actingAs($admin, 'sanctum')
            ->withSession(['organization_id' => $organization->id])
            ->withHeaders(['X-Organization-ID' => $organization->id])
            ->postJson("/api/v1/admin/reservations/{$newReservation->id}/schedule", [
                'scheduled_at' => $scheduledAt->copy()->addHours(6)->toDateTimeString(),
                'biplaceur_id' => $biplaceur->id, // schedule() utilise biplaceur_id et valide les contraintes
                'site_id' => $site->id,
                'tandem_glider_id' => $tandemGlider->id,
                'vehicle_id' => $vehicle->id,
            ]);

        // Devrait être rejeté car limite atteinte (5 vols max)
        $response->assertStatus(400); // schedule() retourne 400 avec message d'erreur
        $response->assertJson([
            'success' => false,
        ]);
    }

    /**
     * Test validation pause obligatoire entre vols biplaceur
     */
    public function test_validates_mandatory_break_between_flights(): void
    {
        // Créer une organisation
        $organization = \App\Models\Organization::factory()->create();
        
        $biplaceur = Biplaceur::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $site = Site::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $tandemGlider = Resource::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'tandem_glider'
        ]);
        $vehicle = Resource::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'vehicle'
        ]);

        // Créer une réservation assignée à 10h00
        $firstReservation = Reservation::factory()->create([
            'organization_id' => $organization->id,
            'biplaceur_id' => $biplaceur->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay()->setTime(10, 0),
        ]);

        // Tenter d'assigner une deuxième réservation à 10h15 (trop tôt, besoin de 30 min de pause)
        $newReservation = Reservation::factory()->create([
            'status' => 'pending',
            'organization_id' => $organization->id,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $organization->users()->attach($admin->id, ['role' => 'admin', 'permissions' => ['*']]);

        $response = $this->actingAs($admin, 'sanctum')
            ->withSession(['organization_id' => $organization->id])
            ->withHeaders(['X-Organization-ID' => $organization->id])
            ->putJson("/api/v1/admin/reservations/{$newReservation->id}/assign", [
                'scheduled_at' => now()->addDay()->setTime(10, 15)->toDateTimeString(),
                'biplaceur_id' => $biplaceur->id,
                'site_id' => $site->id,
                'tandem_glider_id' => $tandemGlider->id,
                'vehicle_id' => $vehicle->id,
            ]);

        // Devrait être rejeté car pas assez de temps entre les vols
        $response->assertStatus(422);
    }
}

