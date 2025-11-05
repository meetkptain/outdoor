<?php

namespace Tests\Feature\Api;

use App\Models\Option;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test création d'une réservation publique
     */
    public function test_can_create_reservation(): void
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

        // Créer une option active pour les tests
        $option = Option::factory()->create([
            'is_active' => true,
            'price' => 20.00,
        ]);

        $data = [
            'customer_email' => $this->faker->email,
            'customer_first_name' => $this->faker->firstName,
            'customer_last_name' => $this->faker->lastName,
            'customer_phone' => '+33612345678',
            'customer_weight' => 75,
            'flight_type' => 'tandem',
            'participants_count' => 1,
            'options' => [
                ['id' => $option->id, 'quantity' => 1],
            ],
            'payment_type' => 'deposit',
            'payment_method_id' => 'pm_test_1234567890',
        ];

        $response = $this->postJson('/api/v1/reservations', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reservation' => [
                        'id',
                        'uuid',
                        'customer_email',
                        'status',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('reservations', [
            'customer_email' => $data['customer_email'],
            'status' => 'pending',
        ]);
    }

    /**
     * Test récupération d'une réservation par UUID
     */
    public function test_can_get_reservation_by_uuid(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/v1/reservations/{$reservation->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'uuid',
                    'customer_email',
                ],
            ]);
    }

    /**
     * Test validation des données de réservation
     */
    public function test_validates_reservation_data(): void
    {
        $response = $this->postJson('/api/v1/reservations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'customer_email',
                'customer_first_name',
                'customer_last_name',
                'flight_type',
                'participants_count',
                'payment_method_id',
            ]);
    }
}

