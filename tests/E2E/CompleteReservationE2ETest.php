<?php

namespace Tests\E2E;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Coupon;
use App\Models\Instructor;
use App\Models\Option;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Test E2E : Scénario complet de réservation
 * 
 * Ce test simule un scénario utilisateur complet :
 * 1. Client crée une réservation
 * 2. Application d'un coupon
 * 3. Paiement (autorisation)
 * 4. Admin assigne date/ressources
 * 5. Client ajoute des options
 * 6. Admin capture le paiement
 * 7. Admin marque comme complété
 */
class CompleteReservationE2ETest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Activity $activity;
    protected User $client;
    protected User $admin;
    protected Instructor $instructor;
    protected Site $site;
    protected Resource $equipment;
    protected Option $option;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['cache.default' => 'array']);
        Cache::flush();
        
        // Setup organisation
        $this->organization = Organization::factory()->create();
        
        // Setup activité
        $this->activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'pricing_config' => [
                'base_price' => 120.00,
            ],
        ]);
        
        // Setup client
        $this->client = User::factory()->create();
        $this->client->organizations()->attach($this->organization->id, ['role' => 'client']);
        Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->client->id,
        ]);
        
        // Setup admin
        $this->admin = User::factory()->admin()->create();
        $this->admin->organizations()->attach($this->organization->id, ['role' => 'admin']);
        
        // Setup ressources
        $this->instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
        ]);
        
        $this->site = Site::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        
        $this->equipment = Resource::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'tandem_glider',
        ]);
        
        // Setup option
        $this->option = Option::factory()->create([
            'organization_id' => $this->organization->id,
            'price' => 25.00,
        ]);
        
        // Mock PaymentService pour éviter les appels Stripe réels
        $this->mockPaymentService();
    }

    protected function mockPaymentService(): void
    {
        $paymentServiceMock = $this->mock(\App\Services\PaymentService::class);
        
        // Mock createPaymentIntent
        $paymentServiceMock->shouldReceive('createPaymentIntent')
            ->andReturnUsing(function ($reservation, $amount, $type) {
                return Payment::factory()->create([
                    'organization_id' => $this->organization->id,
                    'reservation_id' => $reservation->id,
                    'amount' => $amount,
                    'status' => $type === 'authorization' ? 'authorized' : 'requires_capture',
                    'type' => $type,
                    'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
                    'stripe_data' => [
                        'client_secret' => 'pi_test_secret_' . uniqid(),
                    ],
                ]);
            });
        
        $paymentServiceMock->shouldReceive('createAdditionalPayment')
            ->andReturnUsing(function ($reservation, $amount, $paymentMethodId) {
                return Payment::factory()->create([
                    'organization_id' => $this->organization->id,
                    'reservation_id' => $reservation->id,
                    'amount' => $amount,
                    'status' => 'requires_capture',
                    'type' => 'additional',
                    'stripe_payment_intent_id' => 'pi_test_additional_' . uniqid(),
                    'payment_method_id' => $paymentMethodId,
                ]);
            });
        
        // Mock capturePayment
        $paymentServiceMock->shouldReceive('capturePayment')
            ->andReturnUsing(function ($payment, $amount = null) {
                $payment->update([
                    'status' => 'captured',
                    'captured_at' => now(),
                ]);
                return true;
            });
        
        $this->app->instance(\App\Services\PaymentService::class, $paymentServiceMock);
    }

    public function test_complete_reservation_flow_with_coupon_and_options(): void
    {
        // Créer un coupon
        $coupon = Coupon::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'TEST2024',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_purchase_amount' => 0,
            'max_discount' => null,
            'applicable_flight_types' => ['paragliding'],
            'is_active' => true,
        ]);

        // ========== ÉTAPE 1 : Client crée une réservation avec coupon ==========
        $reservationData = [
            'customer_email' => 'john.doe@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'customer_phone' => '+33612345678',
            'customer_weight' => 75,
            'customer_height' => 175,
            'activity_id' => $this->activity->id,
            'participants_count' => 1,
            'coupon_code' => 'TEST2024',
            'payment_type' => 'authorization',
            'payment_method_id' => 'pm_test_1234567890',
        ];

        $response = $this->withHeader('X-Organization-ID', $this->organization->id)
            ->postJson('/api/v1/reservations', $reservationData);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        $reservationData = $response->json('data');
        $this->assertNotNull($reservationData);
        
        $reservation = Reservation::where('customer_email', 'john.doe@example.com')->first();
        $this->assertNotNull($reservation);
        $this->assertEquals('pending', $reservation->status);
        $this->assertEquals('TEST2024', $reservation->coupon_code);
        $this->assertGreaterThan(0, $reservation->discount_amount); // 10% de réduction

        // Vérifier qu'un PaymentIntent a été créé
        $payment = $reservation->payments()->first();
        $this->assertNotNull($payment);
        $this->assertContains($payment->status, ['authorized', 'requires_capture']);

        // ========== ÉTAPE 2 : Admin assigne date et ressources ==========
        $scheduledAt = now()->addDays(7)->setTime(10, 0);

        $assignResponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->putJson("/api/v1/admin/reservations/{$reservation->id}/assign", [
                'scheduled_at' => $scheduledAt->toDateTimeString(),
                'instructor_id' => $this->instructor->id,
                'site_id' => $this->site->id,
                'equipment_id' => $this->equipment->id,
            ]);

        $assignResponse->assertStatus(200);

        $reservation->refresh();
        $this->assertEquals('scheduled', $reservation->status);
        $this->assertNotNull($reservation->scheduled_at);
        $this->assertEquals($this->instructor->id, $reservation->instructor_id);
        $this->assertEquals($this->site->id, $reservation->site_id);

        // ========== ÉTAPE 3 : Client ajoute des options ==========
        $addOptionsResponse = $this->withHeader('X-Organization-ID', $this->organization->id)
            ->postJson("/api/v1/reservations/{$reservation->uuid}/add-options", [
            'options' => [
                [
                    'id' => $this->option->id,
                    'quantity' => 1,
                ],
            ],
            'stage' => 'before_flight',
            'payment_method_id' => 'pm_test_additional_123',
        ]);

        $addOptionsResponse->assertStatus(200);

        $reservation->refresh();
        $this->assertGreaterThan(0, $reservation->options_amount);
        $this->assertTrue($reservation->options()->where('options.id', $this->option->id)->exists());

        // ========== ÉTAPE 4 : Admin capture le paiement ==========
        $captureResponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->postJson("/api/v1/admin/reservations/{$reservation->id}/capture");

        $captureResponse->assertStatus(200);

        $payment->refresh();
        $this->assertEquals('captured', $payment->status);
        $this->assertNotNull($payment->captured_at);

        // ========== ÉTAPE 5 : Admin marque comme complété ==========
        $completeResponse = $this->actingAs($this->admin, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->postJson("/api/v1/admin/reservations/{$reservation->id}/complete");

        $completeResponse->assertStatus(200);

        $reservation->refresh();
        $this->assertEquals('completed', $reservation->status);

        $session = $reservation->activitySessions()->first();
        if ($session) {
            $this->assertEquals('completed', $session->status);
        }

        // ========== VÉRIFICATIONS FINALES ==========
        // Vérifier l'historique
        $actions = $reservation->history()->pluck('action');
        $this->assertGreaterThan(0, $actions->count());
        $this->assertTrue($actions->contains('created'));
        $this->assertTrue($actions->contains('assigned') || $actions->contains('scheduled'));
        $this->assertTrue($actions->contains('options_added'));

        // Vérifier les montants finaux
        $this->assertGreaterThan(0, $reservation->base_amount);
        $this->assertGreaterThan(0, $reservation->options_amount);
        $this->assertGreaterThan(0, $reservation->discount_amount);
        $this->assertEquals(
            $reservation->base_amount + $reservation->options_amount - $reservation->discount_amount,
            $reservation->total_amount
        );
    }
}
