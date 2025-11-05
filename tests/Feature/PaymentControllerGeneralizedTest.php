<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Instructor;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentControllerGeneralizedTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Activity $activity;
    protected Instructor $instructor;
    protected User $instructorUser;
    protected Reservation $reservation;
    protected Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_type' => 'paragliding',
            'is_active' => true,
        ]);

        $this->instructorUser = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $this->organization->users()->attach($this->instructorUser->id, ['role' => 'instructor']);

        $this->instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->instructorUser->id,
            'activity_types' => ['paragliding'],
            'metadata' => [
                'can_tap_to_pay' => true,
                'stripe_terminal_location_id' => 'loc_test_123',
            ],
        ]);

        $this->reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'activity_id' => $this->activity->id,
            'activity_type' => 'paragliding',
            'instructor_id' => $this->instructor->id,
            'base_amount' => 100,
            'options_amount' => 0,
            'total_amount' => 100,
            'deposit_amount' => 50,
        ]);

        $this->payment = Payment::factory()->create([
            'organization_id' => $this->organization->id,
            'reservation_id' => $this->reservation->id,
            'status' => 'requires_capture',
            'amount' => 50,
            'type' => 'deposit',
        ]);

        $this->instructorUser->setCurrentOrganization($this->organization);
    }

    public function test_instructor_can_get_terminal_connection_token(): void
    {
        // Mock Stripe Terminal service pour éviter les appels réels
        $this->mock(\App\Services\StripeTerminalService::class, function ($mock) {
            $mock->shouldReceive('getConnectionToken')
                ->once()
                ->with($this->instructor->id)
                ->andReturn('connection_token_test_123');
        });

        $response = $this->actingAs($this->instructorUser, 'sanctum')
            ->postJson('/api/v1/payments/terminal/connection-token');

        // Note: Si Stripe n'est pas configuré, cela peut échouer, mais on teste la structure
        // Le test vérifie que le code utilise instructor_id et non biplaceur_id
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'connection_token',
                ],
            ]);
    }

    public function test_instructor_can_capture_payment_for_their_reservation(): void
    {
        // Mock PaymentService pour éviter les appels Stripe
        $this->mock(\App\Services\PaymentService::class, function ($mock) {
            $mock->shouldReceive('capturePayment')
                ->once()
                ->andReturn(true);
        });

        $response = $this->actingAs($this->instructorUser, 'sanctum')
            ->postJson('/api/v1/payments/capture', [
                'payment_id' => $this->payment->id,
                'amount' => 50,
            ]);

        // Note: Peut échouer si Stripe n'est pas configuré, mais on teste la logique
        // Le test vérifie que l'instructeur peut capturer pour sa réservation
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
            ]);
    }

    public function test_instructor_cannot_capture_payment_for_other_reservation(): void
    {
        // Créer un autre instructeur
        $otherInstructorUser = User::factory()->create();
        $this->organization->users()->attach($otherInstructorUser->id, ['role' => 'instructor']);
        
        $otherInstructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $otherInstructorUser->id,
        ]);

        $otherInstructorUser->setCurrentOrganization($this->organization);

        $response = $this->actingAs($otherInstructorUser, 'sanctum')
            ->postJson('/api/v1/payments/capture', [
                'payment_id' => $this->payment->id,
                'amount' => 50,
            ]);

        // Devrait échouer car ce n'est pas sa réservation (vérification basée sur instructor_id)
        $response->assertStatus(403);
    }
}

