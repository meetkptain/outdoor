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

class ActivityConstraintsTest extends TestCase
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

    public function test_requires_metadata_defined_in_constraints(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'surfing',
            'constraints_config' => [
                'required_metadata' => ['swimming_level'],
            ],
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/metadata\.swimming_level/');

        $this->service->createReservation([
            'activity_id' => $activity->id,
            'participants_count' => 1,
            'customer_email' => 'surf@example.com',
            'customer_first_name' => 'Surf',
            'customer_last_name' => 'Test',
            'payment_type' => 'deposit',
        ]);
    }

    public function test_accepts_reservation_when_metadata_is_present(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'surfing',
            'min_participants' => 1,
            'max_participants' => 6,
            'constraints_config' => [
                'required_metadata' => ['swimming_level'],
                'participants' => ['min' => 1, 'max' => 6],
            ],
        ]);

        $reservation = $this->service->createReservation([
            'activity_id' => $activity->id,
            'participants_count' => 3,
            'customer_email' => 'surf@example.com',
            'customer_first_name' => 'Surf',
            'customer_last_name' => 'Test',
            'payment_type' => 'deposit',
            'metadata' => [
                'swimming_level' => 'advanced',
            ],
        ]);

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertEquals(3, $reservation->participants_count);
        $this->assertEquals('surfing', $reservation->activity_type);
    }
}


