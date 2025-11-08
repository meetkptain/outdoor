<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\Reservation;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\VehicleService;
use App\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ActivityPricingTest extends TestCase
{
    use RefreshDatabase;

    protected ReservationService $service;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $paymentService = Mockery::mock(PaymentService::class);
        $notificationService = Mockery::mock(NotificationService::class);
        $vehicleService = Mockery::mock(VehicleService::class);
        $moduleRegistry = app(ModuleRegistry::class);

        $this->service = new ReservationService(
            $paymentService,
            $notificationService,
            $vehicleService,
            $moduleRegistry,
            app(\App\Services\InstructorService::class)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_per_participant_pricing_model(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'min_participants' => 1,
            'max_participants' => 6,
            'pricing_config' => [
                'model' => 'per_participant',
                'price_per_participant' => 150,
            ],
        ]);

        $reservation = $this->service->createReservation([
            'activity_id' => $activity->id,
            'participants_count' => 2,
            'customer_email' => 'price@example.com',
            'customer_first_name' => 'Price',
            'customer_last_name' => 'Test',
            'payment_type' => 'deposit',
        ]);

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertEquals(300.0, (float) $reservation->base_amount);
    }

    public function test_tiered_pricing_model(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'diving',
            'min_participants' => 1,
            'max_participants' => 6,
            'pricing_config' => [
                'model' => 'tiered',
                'tiers' => [
                    ['max' => 2, 'price' => 200],
                    ['max' => 4, 'per_participant' => 90],
                ],
            ],
        ]);

        $reservation = $this->service->createReservation([
            'activity_id' => $activity->id,
            'participants_count' => 3,
            'customer_email' => 'tier@example.com',
            'customer_first_name' => 'Tier',
            'customer_last_name' => 'Test',
            'payment_type' => 'deposit',
        ]);

        $this->assertEquals(270.0, (float) $reservation->base_amount);
    }

    public function test_per_duration_pricing_model(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'yoga',
            'duration_minutes' => 90,
            'min_participants' => 1,
            'max_participants' => 6,
            'pricing_config' => [
                'model' => 'per_duration',
                'per_duration' => [
                    'unit_minutes' => 30,
                    'unit_price' => 40,
                    'minimum_price' => 80,
                    'per_participant' => true,
                ],
            ],
        ]);

        $reservation = $this->service->createReservation([
            'activity_id' => $activity->id,
            'participants_count' => 2,
            'customer_email' => 'duration@example.com',
            'customer_first_name' => 'Duration',
            'customer_last_name' => 'Test',
            'payment_type' => 'deposit',
        ]);

        // 90 minutes => 3 unités x 40 = 120, multiplié par 2 participants = 240
        $this->assertEquals(240.0, (float) $reservation->base_amount);
    }
}


