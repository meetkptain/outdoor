<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\ActivitySession;
use App\Models\Organization;
use App\Models\Reservation;
use App\Services\InstructorService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\VehicleService;
use App\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ActivitySessionCreationTest extends TestCase
{
    use RefreshDatabase;

    protected ReservationService $service;
    protected Organization $organization;
    protected ModuleRegistry $moduleRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->moduleRegistry = app(ModuleRegistry::class);

        $paymentService = Mockery::mock(PaymentService::class);
        $notificationService = Mockery::mock(NotificationService::class);
        $vehicleService = Mockery::mock(VehicleService::class);
        $instructorService = new InstructorService($this->moduleRegistry);

        $this->service = new ReservationService(
            $paymentService,
            $notificationService,
            $vehicleService,
            $this->moduleRegistry,
            $instructorService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_creates_session_per_participant_when_strategy_requires_it(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'surfing',
            'duration_minutes' => 60,
            'min_participants' => 1,
            'max_participants' => 6,
            'metadata' => ['session_strategy' => 'per_participant'],
        ]);

        $reservation = $this->service->createReservation([
            'activity_id' => $activity->id,
            'participants_count' => 3,
            'customer_email' => 'multi@example.com',
            'customer_first_name' => 'Multi',
            'customer_last_name' => 'Participant',
            'payment_type' => 'deposit',
            'metadata' => ['swimming_level' => 'advanced'],
            'participants' => [
                ['first_name' => 'Alice'],
                ['first_name' => 'Bob'],
                ['first_name' => 'Charlie'],
            ],
        ]);

        $sessions = $reservation->activitySessions;
        $this->assertCount(3, $sessions);
        $this->assertTrue($sessions->every(fn(ActivitySession $session) => $session->status === 'pending'));
        $this->assertEquals('Multi', $reservation->customer_first_name);
        $this->assertEquals('Alice', $sessions->first()->metadata['participant']['first_name'] ?? null);
    }

    public function test_creates_single_session_when_strategy_is_per_reservation(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'duration_minutes' => 90,
            'min_participants' => 1,
            'max_participants' => 4,
            // aucun session_strategy => per_reservation
        ]);

        $reservation = $this->service->createReservation([
            'activity_id' => $activity->id,
            'participants_count' => 2,
            'customer_email' => 'single@example.com',
            'customer_first_name' => 'Single',
            'customer_last_name' => 'Session',
            'payment_type' => 'deposit',
        ]);

        $sessions = $reservation->activitySessions;
        $this->assertCount(1, $sessions);
        $this->assertEquals(2, $sessions->first()->metadata['participants_count']);
        $this->assertEquals('pending', $sessions->first()->status);
    }
}


