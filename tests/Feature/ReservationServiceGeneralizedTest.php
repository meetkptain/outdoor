<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Instructor;
use App\Models\Organization;
use App\Models\Reservation;
use App\Services\InstructorService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\VehicleService;
use App\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class ReservationServiceGeneralizedTest extends TestCase
{
    use RefreshDatabase;

    protected ReservationService $service;
    protected $paymentServiceMock;
    protected $notificationServiceMock;
    protected $vehicleServiceMock;
    protected ModuleRegistry $moduleRegistry;
    protected InstructorService $instructorService;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->paymentServiceMock = Mockery::mock(PaymentService::class);
        $this->notificationServiceMock = Mockery::mock(NotificationService::class);
        $this->vehicleServiceMock = Mockery::mock(VehicleService::class);
        $this->vehicleServiceMock
            ->shouldReceive('canAssignReservationToVehicle')
            ->zeroOrMoreTimes()
            ->andReturn(['can_assign' => true, 'errors' => []]);

        $this->moduleRegistry = app(ModuleRegistry::class);
        $this->instructorService = new InstructorService($this->moduleRegistry);

        $this->service = new ReservationService(
            $this->paymentServiceMock,
            $this->notificationServiceMock,
            $this->vehicleServiceMock,
            $this->moduleRegistry,
            $this->instructorService
        );
    }

    public function test_creates_sessions_per_participant_when_strategy_requires_it(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'max_participants' => 4,
            'metadata' => ['session_strategy' => 'per_participant'],
            'pricing_config' => [
                'model' => 'per_participant',
                'price_per_participant' => 120,
            ],
        ]);

        $data = [
            'activity_id' => $activity->id,
            'participants_count' => 2,
            'customer_email' => 'group@example.com',
            'customer_first_name' => 'Group',
            'customer_last_name' => 'Test',
            'payment_type' => 'deposit',
            'participants' => [
                ['first_name' => 'Alice', 'weight' => 60],
                ['first_name' => 'Bob', 'weight' => 75],
            ],
        ];

        $reservation = $this->service->createReservation($data);

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertEquals(2, $reservation->activitySessions()->count());

        $firstSession = $reservation->activitySessions()->first();
        $this->assertEquals('Alice', $firstSession->metadata['participant']['first_name']);
        $this->assertEquals('pending', $firstSession->status);
    }

    public function test_calculates_tiered_pricing_model(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'max_participants' => 6,
            'pricing_config' => [
                'model' => 'tiered',
                'tiers' => [
                    ['max' => 2, 'price' => 150],
                    ['max' => 4, 'per_participant' => 70],
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

        $this->assertEquals(210.0, (float) $reservation->base_amount);
    }

    public function test_requires_metadata_field_defined_in_constraints(): void
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

    public function test_assign_resources_updates_sessions_and_metadata(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'max_participants' => 4,
            'metadata' => ['session_strategy' => 'per_participant'],
        ]);

        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_types' => ['paragliding'],
            'max_sessions_per_day' => 5,
            'certifications' => [],
        ]);

        $reservation = $this->service->createReservation([
            'activity_id' => $activity->id,
            'participants_count' => 2,
            'customer_email' => 'assign@example.com',
            'customer_first_name' => 'Assign',
            'customer_last_name' => 'Test',
            'payment_type' => 'deposit',
            'participants' => [
                ['first_name' => 'Alice'],
                ['first_name' => 'Bob'],
            ],
        ]);

        $scheduledAt = now()->addDay()->setTime(9, 0);

        $this->notificationServiceMock
            ->shouldReceive('sendAssignmentNotification')
            ->once();
        $this->notificationServiceMock
            ->shouldReceive('scheduleReminder')
            ->once();

        $this->service->assignResources(
            $reservation,
            new \DateTime($scheduledAt->format('Y-m-d H:i:s')),
            $instructor->id,
            null,
            123,
            null
        );

        $reservation->refresh();
        $this->assertEquals('scheduled', $reservation->status);
        $this->assertEquals(123, $reservation->metadata['equipment_id']);

        foreach ($reservation->activitySessions as $session) {
            $this->assertEquals('scheduled', $session->status);
            $this->assertEquals($instructor->id, $session->instructor_id);
            $this->assertEquals(123, $session->metadata['equipment_id']);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}


