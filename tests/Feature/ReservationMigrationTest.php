<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Reservation;
use App\Models\Activity;
use App\Models\Instructor;
use App\Models\ActivitySession;
use App\Models\Biplaceur;
use App\Models\Flight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ReservationMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Activity $paraglidingActivity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        
        // Créer activité paragliding
        $this->paraglidingActivity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'name' => 'Parapente',
        ]);
    }

    public function test_reservation_has_activity_id_after_migration(): void
    {
        // Créer une réservation avec flight_type (ancien format)
        $reservation = Reservation::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'activity_id' => $this->paraglidingActivity->id,
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'flight_type' => 'tandem',
            'participants_count' => 1,
            'status' => 'pending',
            'base_amount' => 120.00,
            'options_amount' => 0.00,
            'total_amount' => 120.00,
            'deposit_amount' => 0.00,
        ]);

        $this->assertNotNull($reservation->activity_id);
        $this->assertEquals('paragliding', $reservation->activity_type);
        $this->assertEquals($this->paraglidingActivity->id, $reservation->activity_id);
    }

    public function test_reservation_has_instructor_id_after_biplaceur_migration(): void
    {
        $user = User::factory()->create();
        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'activity_types' => json_encode(['paragliding']),
        ]);

        // Créer une réservation avec instructor_id
        $reservation = Reservation::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->paraglidingActivity->id,
            'activity_type' => 'paragliding',
            'instructor_id' => $instructor->id,
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'participants_count' => 1,
            'status' => 'pending',
            'base_amount' => 120.00,
            'options_amount' => 0.00,
            'total_amount' => 120.00,
            'deposit_amount' => 0.00,
        ]);

        $this->assertNotNull($reservation->instructor_id);
        $this->assertEquals($instructor->id, $reservation->instructor_id);
        $this->assertInstanceOf(Instructor::class, $reservation->instructor);
    }

    public function test_reservation_has_activity_sessions_relation(): void
    {
        $reservation = Reservation::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->paraglidingActivity->id,
            'activity_type' => 'paragliding',
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'participants_count' => 1,
            'status' => 'pending',
            'base_amount' => 120.00,
            'options_amount' => 0.00,
            'total_amount' => 120.00,
            'deposit_amount' => 0.00,
        ]);

        // Créer une session d'activité
        $session = ActivitySession::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->paraglidingActivity->id,
            'reservation_id' => $reservation->id,
        ]);

        $this->assertCount(1, $reservation->activitySessions);
        $this->assertEquals($session->id, $reservation->activitySessions->first()->id);
    }

    public function test_reservation_get_equipment_from_metadata(): void
    {
        $equipment = \App\Models\Resource::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'equipment',
        ]);

        $reservation = Reservation::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->paraglidingActivity->id,
            'activity_type' => 'paragliding',
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'participants_count' => 1,
            'status' => 'pending',
            'metadata' => ['equipment_id' => $equipment->id],
            'base_amount' => 120.00,
            'options_amount' => 0.00,
            'total_amount' => 120.00,
            'deposit_amount' => 0.00,
        ]);

        $retrievedEquipment = $reservation->getEquipment();
        $this->assertNotNull($retrievedEquipment);
        $this->assertEquals($equipment->id, $retrievedEquipment->id);
    }

    public function test_reservation_set_equipment_in_metadata(): void
    {
        $equipment = \App\Models\Resource::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => 'equipment',
        ]);

        $reservation = Reservation::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->paraglidingActivity->id,
            'activity_type' => 'paragliding',
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'participants_count' => 1,
            'status' => 'pending',
            'base_amount' => 120.00,
            'options_amount' => 0.00,
            'total_amount' => 120.00,
            'deposit_amount' => 0.00,
        ]);

        $reservation->setEquipment($equipment->id);
        $reservation->refresh();

        $this->assertEquals($equipment->id, $reservation->metadata['equipment_id']);
    }

    public function test_reservation_instructor_relation_works(): void
    {
        $user = User::factory()->create();
        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'activity_types' => json_encode(['paragliding']),
        ]);

        $reservation = Reservation::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->paraglidingActivity->id,
            'activity_type' => 'paragliding',
            'instructor_id' => $instructor->id,
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'participants_count' => 1,
            'status' => 'pending',
            'base_amount' => 120.00,
            'options_amount' => 0.00,
            'total_amount' => 120.00,
            'deposit_amount' => 0.00,
        ]);

        $loadedReservation = Reservation::withoutGlobalScopes()->with('instructor')->find($reservation->id);
        $this->assertNotNull($loadedReservation->instructor);
        $this->assertInstanceOf(Instructor::class, $loadedReservation->instructor);
        $this->assertEquals($instructor->id, $loadedReservation->instructor->id);
    }

    public function test_flight_type_stored_in_metadata_after_migration(): void
    {
        $reservation = Reservation::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->paraglidingActivity->id,
            'activity_type' => 'paragliding',
            'customer_email' => 'test@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'participants_count' => 1,
            'status' => 'pending',
            'metadata' => ['original_flight_type' => 'tandem'],
            'base_amount' => 120.00,
            'options_amount' => 0.00,
            'total_amount' => 120.00,
            'deposit_amount' => 0.00,
        ]);

        $this->assertEquals('tandem', $reservation->metadata['original_flight_type'] ?? null);
    }
}
