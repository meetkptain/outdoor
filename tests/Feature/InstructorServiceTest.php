<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySession;
use App\Models\Instructor;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Site;
use App\Models\User;
use App\Services\InstructorService;
use App\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class InstructorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InstructorService $service;
    protected Organization $organization;
    protected Activity $activity;
    protected Instructor $instructor;
    protected User $instructorUser;
    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $moduleRegistry = app(ModuleRegistry::class);
        $this->service = new InstructorService($moduleRegistry);

        // Créer une organisation
        $this->organization = Organization::factory()->create();

        // Créer une activité
        $this->activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'name' => 'Vol en parapente',
            'is_active' => true,
        ]);

        // Créer un utilisateur instructeur
        $this->instructorUser = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        // Attacher l'utilisateur à l'organisation
        $this->organization->users()->attach($this->instructorUser->id, [
            'role' => 'instructor',
            'permissions' => [],
        ]);

        // Créer un instructeur
        $this->instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->instructorUser->id,
            'activity_types' => ['paragliding'],
            'availability' => [
                'days' => [1, 2, 3, 4, 5], // Lundi-Vendredi
                'hours' => [8, 9, 10, 11, 12, 13, 14, 15, 16, 17],
                'exceptions' => [],
            ],
            'max_sessions_per_day' => 5,
            'is_active' => true,
        ]);

        // Créer un site
        $this->site = Site::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_can_get_sessions_today(): void
    {
        // Créer une réservation
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'status' => 'scheduled',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        // Créer une session pour aujourd'hui
        $sessionToday = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => today()->setTime(10, 0),
            'status' => 'scheduled',
        ]);

        // Créer une session pour demain (ne devrait pas être incluse)
        $sessionTomorrow = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => today()->addDay()->setTime(10, 0),
            'status' => 'scheduled',
        ]);

        $sessions = $this->service->getSessionsToday($this->instructor->id);

        $this->assertCount(1, $sessions);
        $this->assertEquals($sessionToday->id, $sessions->first()->id);
    }

    public function test_can_get_calendar(): void
    {
        $startDate = today();
        $endDate = today()->addDays(7);

        // Créer plusieurs réservations
        $reservation1 = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'status' => 'scheduled',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        $reservation2 = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'status' => 'scheduled',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        // Créer des sessions dans la plage
        $session1 = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation1->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => $startDate->copy()->addDays(2)->setTime(10, 0),
            'status' => 'scheduled',
        ]);

        $session2 = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation2->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => $startDate->copy()->addDays(5)->setTime(14, 0),
            'status' => 'completed',
        ]);

        // Créer une session en dehors de la plage (ne devrait pas être incluse)
        $session3 = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation1->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => $endDate->copy()->addDays(2)->setTime(10, 0),
            'status' => 'scheduled',
        ]);

        $calendar = $this->service->getCalendar(
            $this->instructor->id,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $this->assertCount(2, $calendar);
        $this->assertTrue($calendar->contains('id', $session1->id));
        $this->assertTrue($calendar->contains('id', $session2->id));
        $this->assertFalse($calendar->contains('id', $session3->id));
    }

    public function test_can_update_availability(): void
    {
        $newAvailability = [
            'days' => [1, 2, 3, 4, 5, 6], // Lundi-Samedi
            'hours' => [9, 10, 11, 12, 13, 14, 15, 16],
            'exceptions' => ['2025-12-25'],
        ];

        $result = $this->service->updateAvailability($this->instructor->id, $newAvailability);

        $this->assertTrue($result);
        $this->instructor->refresh();
        $this->assertEquals($newAvailability, $this->instructor->availability);
    }

    public function test_can_mark_session_done(): void
    {
        // Créer une réservation
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'status' => 'scheduled',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        // Créer une session
        $session = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        $updatedSession = $this->service->markSessionDone($session->id, $this->instructor->id);

        $this->assertEquals('completed', $updatedSession->status);
        $reservation->refresh();
        $this->assertEquals('completed', $reservation->status);
    }

    public function test_can_reschedule_session(): void
    {
        // Créer une réservation
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'status' => 'scheduled',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        // Créer une session
        $session = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        $reason = 'Client demande report';
        $updatedSession = $this->service->rescheduleSession($session->id, $this->instructor->id, $reason);

        $this->assertEquals('cancelled', $updatedSession->status);
        $reservation->refresh();
        $this->assertEquals('rescheduled', $reservation->status);
    }

    public function test_can_check_availability(): void
    {
        $validDate = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
        $available = $this->service->isAvailable(
            $this->instructor->id,
            $validDate,
            '10:00:00'
        );
        $this->assertTrue($available);

        // Créer une session existante
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'status' => 'scheduled',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => Carbon::parse($validDate)->setTime(10, 0),
            'status' => 'scheduled',
        ]);

        // Test disponibilité occupée
        $notAvailable = $this->service->isAvailable(
            $this->instructor->id,
            $validDate,
            '10:00:00'
        );
        $this->assertFalse($notAvailable);
    }

    public function test_can_get_stats(): void
    {
        // Créer plusieurs réservations
        $reservation1 = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'status' => 'scheduled',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        $reservation2 = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'status' => 'scheduled',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        // Créer des sessions
        ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation1->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => now(),
            'status' => 'completed',
        ]);

        ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation2->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $stats = $this->service->getStats($this->instructor->id);

        $this->assertEquals(2, $stats['total_sessions']);
        $this->assertEquals(1, $stats['completed_sessions']);
        $this->assertEquals(1, $stats['scheduled_sessions']);
        $this->assertEquals(50.0, $stats['completion_rate']);
    }

    public function test_can_get_upcoming_sessions(): void
    {
        // Créer plusieurs réservations
        $reservation1 = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'status' => 'scheduled',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        $reservation2 = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'status' => 'scheduled',
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        // Créer une session passée (ne devrait pas être incluse)
        ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation1->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => now()->subDay(),
            'status' => 'scheduled',
        ]);

        // Créer des sessions futures
        $session1 = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation1->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $session2 = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'reservation_id' => $reservation2->id,
            'instructor_id' => $this->instructor->id,
            'site_id' => $this->site->id,
            'scheduled_at' => now()->addDays(2),
            'status' => 'scheduled',
        ]);

        $upcoming = $this->service->getUpcomingSessions($this->instructor->id, 10);

        $this->assertCount(2, $upcoming);
        $this->assertTrue($upcoming->contains('id', $session1->id));
        $this->assertTrue($upcoming->contains('id', $session2->id));
    }
}

