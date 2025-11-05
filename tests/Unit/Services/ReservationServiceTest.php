<?php

namespace Tests\Unit\Services;

use App\Models\Activity;
use App\Models\Coupon;
use App\Models\Option;
use App\Models\Organization;
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
    protected Organization $organization;
    protected Activity $activity;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer une organisation
        $this->organization = Organization::factory()->create();

        // Créer une activité par défaut (paragliding)
        $this->activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'name' => 'Vol en parapente',
            'pricing_config' => [
                'base_price' => 100,
                'price_per_participant' => 80,
            ],
            'constraints_config' => [
                'min_weight' => 40,
                'max_weight' => 120,
                'min_height' => 140,
                'max_height' => 200,
            ],
            'is_active' => true,
        ]);

        $this->paymentServiceMock = Mockery::mock(PaymentService::class);
        $this->notificationServiceMock = Mockery::mock(NotificationService::class);
        $this->vehicleServiceMock = Mockery::mock(VehicleService::class);
        $moduleRegistry = app(\App\Modules\ModuleRegistry::class);

        $this->service = new ReservationService(
            $this->paymentServiceMock,
            $this->notificationServiceMock,
            $this->vehicleServiceMock,
            $moduleRegistry
        );
    }

    /**
     * Test création d'une réservation
     */
    public function test_can_create_reservation(): void
    {
        $data = [
            'organization_id' => $this->organization->id,
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'activity_id' => $this->activity->id,
            'participants_count' => 1,
            'payment_type' => 'deposit',
            'metadata' => [
                'original_flight_type' => 'tandem',
            ],
        ];

        // Mock notification service (pas besoin d'envoyer réellement)
        $this->notificationServiceMock->shouldNotReceive('sendReservationConfirmation');

        $reservation = $this->service->createReservation($data);

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertEquals('pending', $reservation->status);
        $this->assertEquals('test@example.com', $reservation->customer_email);
        $this->assertEquals($this->activity->id, $reservation->activity_id);
        $this->assertEquals('paragliding', $reservation->activity_type);
    }

    /**
     * Test calcul des montants avec coupon
     */
    public function test_calculates_amount_with_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'TEST10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
            'applicable_flight_types' => null, // Accepte tous les types ou spécifiquement 'paragliding'
        ]);

        $data = [
            'organization_id' => $this->organization->id,
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'activity_id' => $this->activity->id,
            'participants_count' => 1,
            'coupon_code' => 'TEST10',
            'payment_type' => 'deposit',
            'metadata' => [
                'original_flight_type' => 'tandem',
            ],
        ];

        $this->notificationServiceMock->shouldNotReceive('sendReservationConfirmation');

        $reservation = $this->service->createReservation($data);

        // Vérifier que le montant de base est calculé
        $this->assertGreaterThan(0, $reservation->base_amount);
        
        // Vérifier que le coupon est appliqué
        $this->assertGreaterThan(0, $reservation->discount_amount);
        $this->assertEquals($coupon->id, $reservation->coupon_id);
        
        // Vérifier que le montant total est réduit
        $this->assertEquals(
            $reservation->base_amount - $reservation->discount_amount,
            $reservation->total_amount
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

