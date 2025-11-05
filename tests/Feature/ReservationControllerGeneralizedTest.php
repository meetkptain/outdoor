<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReservationControllerGeneralizedTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Activity $activity;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'is_active' => true,
            'pricing_config' => [
                'base_price' => 100,
                'price_per_participant' => 50,
            ],
            'constraints_config' => [
                'min_weight' => 30,
                'max_weight' => 150,
                'min_height' => 100,
                'max_height' => 250,
            ],
        ]);

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    }

    public function test_can_create_reservation_with_activity_id(): void
    {
        // Mock PaymentService pour éviter les appels Stripe
        $this->mock(\App\Services\PaymentService::class, function ($mock) {
            $payment = \App\Models\Payment::factory()->make([
                'status' => 'requires_capture',
                'stripe_data' => ['client_secret' => 'test_secret'],
            ]);
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn($payment);
        });

        $response = $this->postJson('/api/v1/reservations', [
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'activity_id' => $this->activity->id,
            'participants_count' => 1,
            'customer_weight' => 70,
            'customer_height' => 175,
            'payment_method_id' => 'pm_test_123',
            'payment_type' => 'deposit',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reservation' => [
                        'id',
                        'activity_id',
                        'activity_type',
                        'uuid',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('reservations', [
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
        ]);
    }

    public function test_can_create_reservation_with_flight_type_deprecated(): void
    {
        // Mock PaymentService pour éviter les appels Stripe
        $this->mock(\App\Services\PaymentService::class, function ($mock) {
            $payment = \App\Models\Payment::factory()->make([
                'status' => 'requires_capture',
                'stripe_data' => ['client_secret' => 'test_secret'],
            ]);
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn($payment);
        });

        // Test rétrocompatibilité avec flight_type
        $response = $this->postJson('/api/v1/reservations', [
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'flight_type' => 'tandem', // @deprecated
            'participants_count' => 1,
            'customer_weight' => 70,
            'customer_height' => 175,
            'payment_method_id' => 'pm_test_123',
            'payment_type' => 'deposit',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reservation' => [
                        'id',
                        'activity_id',
                        'activity_type',
                    ],
                ],
            ]);

        // Vérifier que l'activité paragliding par défaut a été utilisée
        $this->assertDatabaseHas('reservations', [
            'activity_type' => 'paragliding',
        ]);
    }

    public function test_reservation_has_activity_sessions_relation(): void
    {
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        $response = $this->getJson("/api/v1/reservations/{$reservation->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'activity_sessions',
                    'activity',
                ],
            ]);
    }

    public function test_reservation_has_instructor_instead_of_biplaceur(): void
    {
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        $response = $this->getJson("/api/v1/reservations/{$reservation->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'instructor',
                    'activity',
                ],
            ]);
    }

    public function test_apply_coupon_uses_activity_type(): void
    {
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        $coupon = \App\Models\Coupon::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'TEST10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'applicable_flight_types' => ['paragliding'],
        ]);

        $response = $this->postJson("/api/v1/reservations/{$reservation->uuid}/apply-coupon", [
            'coupon_code' => 'TEST10',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}

