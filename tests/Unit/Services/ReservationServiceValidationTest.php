<?php

namespace Tests\Unit\Services;

use App\Models\Reservation;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\NotificationService;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class ReservationServiceValidationTest extends TestCase
{
    use RefreshDatabase;

    protected ReservationService $service;
    protected $paymentServiceMock;
    protected $notificationServiceMock;
    protected $vehicleServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentServiceMock = Mockery::mock(PaymentService::class);
        $this->notificationServiceMock = Mockery::mock(NotificationService::class);
        $this->vehicleServiceMock = Mockery::mock(VehicleService::class);

        $this->service = new ReservationService(
            $this->paymentServiceMock,
            $this->notificationServiceMock,
            $this->vehicleServiceMock
        );
    }

    /**
     * Test validation poids minimum (40kg)
     */
    public function test_rejects_reservation_with_weight_below_minimum(): void
    {
        $data = [
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'flight_type' => 'tandem',
            'participants_count' => 1,
            'customer_weight' => 35, // En dessous du minimum
            'payment_type' => 'deposit',
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Poids minimum requis: 40kg');

        $this->service->createReservation($data);
    }

    /**
     * Test validation poids maximum (120kg)
     */
    public function test_rejects_reservation_with_weight_above_maximum(): void
    {
        $data = [
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'flight_type' => 'tandem',
            'participants_count' => 1,
            'customer_weight' => 125, // Au dessus du maximum
            'payment_type' => 'deposit',
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Poids maximum autorisé: 120kg');

        $this->service->createReservation($data);
    }

    /**
     * Test validation taille minimum (140cm)
     */
    public function test_rejects_reservation_with_height_below_minimum(): void
    {
        $data = [
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'flight_type' => 'tandem',
            'participants_count' => 1,
            'customer_height' => 130, // En dessous du minimum
            'payment_type' => 'deposit',
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Taille minimum requise: 1.40m (140cm)');

        $this->service->createReservation($data);
    }

    /**
     * Test accepte réservation avec poids et taille valides
     */
    public function test_accepts_reservation_with_valid_weight_and_height(): void
    {
        $data = [
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'flight_type' => 'tandem',
            'participants_count' => 1,
            'customer_weight' => 75, // Valide
            'customer_height' => 175, // Valide
            'payment_type' => 'deposit',
        ];

        $this->notificationServiceMock->shouldNotReceive('sendReservationConfirmation');

        $reservation = $this->service->createReservation($data);

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertEquals(75, $reservation->customer_weight);
        $this->assertEquals(175, $reservation->customer_height);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

