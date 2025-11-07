<?php

namespace Tests\E2E;

use App\Models\Activity;
use App\Models\Instructor;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Test E2E : Scénario multi-activités
 * 
 * Ce test simule un scénario utilisateur avec plusieurs activités :
 * 1. Client consulte les activités disponibles
 * 2. Client crée une réservation paragliding
 * 3. Client crée une réservation surfing
 * 4. Vérification de l'isolation des données
 */
class MultiActivityE2ETest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $client;
    protected Activity $paraglidingActivity;
    protected Activity $surfingActivity;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['cache.default' => 'array']);
        Cache::flush();
        
        $this->organization = Organization::factory()->create();
        
        $this->client = User::factory()->create();
        $this->client->organizations()->attach($this->organization->id, ['role' => 'client']);
        
        // Créer des activités de différents types
        $this->paraglidingActivity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'name' => 'Vol Tandem Parapente',
            'pricing_config' => [
                'base_price' => 120.00,
            ],
        ]);
        
        $this->surfingActivity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'surfing',
            'name' => 'Cours de Surf',
            'pricing_config' => [
                'base_price' => 80.00,
            ],
        ]);
        
        // Mock PaymentService
        $this->mockPaymentService();
    }

    protected function mockPaymentService(): void
    {
        $paymentServiceMock = $this->mock(\App\Services\PaymentService::class);
        
        $paymentServiceMock->shouldReceive('createPaymentIntent')
            ->andReturnUsing(function ($reservation, $amount, $type) {
                return \App\Models\Payment::factory()->create([
                    'reservation_id' => $reservation->id,
                    'amount' => $amount,
                    'status' => 'authorized',
                    'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
                ]);
            });
        
        $this->app->instance(\App\Services\PaymentService::class, $paymentServiceMock);
    }

    public function test_multi_activity_reservation_flow(): void
    {
        // ========== ÉTAPE 1 : Client consulte les activités disponibles ==========
        $activitiesResponse = $this->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/activities?activity_type=paragliding');
        
        $activitiesResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        $activities = $activitiesResponse->json('data');
        $this->assertGreaterThan(0, count($activities));
        
        // Vérifier que seule l'activité paragliding est retournée
        foreach ($activities as $activity) {
            $this->assertEquals('paragliding', $activity['activity_type']);
        }

        // ========== ÉTAPE 2 : Client crée une réservation paragliding ==========
        $paraglidingReservationData = [
            'customer_email' => 'client@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'customer_weight' => 75,
            'customer_height' => 175,
            'activity_id' => $this->paraglidingActivity->id,
            'participants_count' => 1,
            'payment_type' => 'deposit',
            'payment_method_id' => 'pm_test_1234567890',
        ];

        $paraglidingResponse = $this->withHeader('X-Organization-ID', $this->organization->id)
            ->postJson('/api/v1/reservations', $paraglidingReservationData);
        
        $paraglidingResponse->assertStatus(201);

        $paraglidingReservation = Reservation::where('activity_id', $this->paraglidingActivity->id)->first();
        $this->assertNotNull($paraglidingReservation);
        $this->assertEquals('paragliding', $paraglidingReservation->activity_type);
        $this->assertGreaterThan(0, $paraglidingReservation->base_amount);

        // ========== ÉTAPE 3 : Client crée une réservation surfing ==========
        $surfingReservationData = [
            'customer_email' => 'client@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'activity_id' => $this->surfingActivity->id,
            'participants_count' => 1,
            'payment_type' => 'deposit',
            'payment_method_id' => 'pm_test_1234567890',
        ];

        $surfingResponse = $this->withHeader('X-Organization-ID', $this->organization->id)
            ->postJson('/api/v1/reservations', $surfingReservationData);
        
        $surfingResponse->assertStatus(201);

        $surfingReservation = Reservation::where('activity_id', $this->surfingActivity->id)->first();
        $this->assertNotNull($surfingReservation);
        $this->assertEquals('surfing', $surfingReservation->activity_type);
        $this->assertGreaterThan(0, $surfingReservation->base_amount);

        // ========== ÉTAPE 4 : Vérification de l'isolation des données ==========
        // Les deux réservations doivent être distinctes
        $this->assertNotEquals($paraglidingReservation->id, $surfingReservation->id);
        $this->assertNotEquals($paraglidingReservation->base_amount, $surfingReservation->base_amount);

        // ========== ÉTAPE 5 : Client consulte ses réservations ==========
        $myReservationsResponse = $this->actingAs($this->client, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/my/reservations');

        $myReservationsResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ]);

        $myReservations = $myReservationsResponse->json('data');
        $this->assertGreaterThanOrEqual(2, count($myReservations));

        // Vérifier que les deux réservations sont présentes
        $activityTypes = collect($myReservations)->pluck('activity_type')->toArray();
        $this->assertContains('paragliding', $activityTypes);
        $this->assertContains('surfing', $activityTypes);

        // ========== ÉTAPE 6 : Filtrage par type d'activité ==========
        $paraglidingOnlyResponse = $this->actingAs($this->client, 'sanctum')
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/my/reservations?activity_type=paragliding');

        $paraglidingOnlyResponse->assertStatus(200);
        
        $paraglidingOnly = $paraglidingOnlyResponse->json('data');
        foreach ($paraglidingOnly as $reservation) {
            $this->assertEquals('paragliding', $reservation['activity_type']);
        }
    }

    public function test_instructor_supports_multiple_activities(): void
    {
        // Créer un instructeur qui supporte plusieurs activités
        $instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create()->id,
            'activity_types' => ['paragliding', 'surfing'],
        ]);

        // Vérifier que l'instructeur apparaît dans les deux listes
        $paraglidingInstructors = $this->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/instructors?activity_type=paragliding');
        $paraglidingInstructors->assertStatus(200);
        
        $surfingInstructors = $this->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/instructors?activity_type=surfing');
        $surfingInstructors->assertStatus(200);

        $paraglidingIds = collect($paraglidingInstructors->json('data'))->pluck('id')->toArray();
        $surfingIds = collect($surfingInstructors->json('data'))->pluck('id')->toArray();

        $this->assertContains($instructor->id, $paraglidingIds);
        $this->assertContains($instructor->id, $surfingIds);
    }
}

