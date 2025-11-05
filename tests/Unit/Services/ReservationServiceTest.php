<?php

namespace Tests\Unit\Services;

use App\Models\Coupon;
use App\Models\Option;
use App\Models\Reservation;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\NotificationService;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class ReservationServiceTest extends TestCase
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
     * Test création d'une réservation
     */
    public function test_can_create_reservation(): void
    {
        $data = [
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'flight_type' => 'tandem',
            'participants_count' => 1,
            'payment_type' => 'deposit',
        ];

        // Mock notification service (pas besoin d'envoyer réellement)
        $this->notificationServiceMock->shouldNotReceive('sendReservationConfirmation');

        $reservation = $this->service->createReservation($data);

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertEquals('pending', $reservation->status);
        $this->assertEquals('test@example.com', $reservation->customer_email);
    }

    /**
     * Test calcul des montants avec coupon
     */
    public function test_calculates_amount_with_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'TEST10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $data = [
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'flight_type' => 'tandem',
            'participants_count' => 1,
            'coupon_code' => 'TEST10',
            'payment_type' => 'deposit',
        ];

        $this->notificationServiceMock->shouldNotReceive('sendReservationConfirmation');

        $reservation = $this->service->createReservation($data);

        $this->assertGreaterThan(0, $reservation->discount_amount);
        $this->assertEquals($coupon->id, $reservation->coupon_id);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

