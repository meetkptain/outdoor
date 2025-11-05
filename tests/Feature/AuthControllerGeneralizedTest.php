<?php

namespace Tests\Feature;

use App\Models\Instructor;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerGeneralizedTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $instructorUser;
    protected Instructor $instructor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->instructorUser = User::factory()->create([
            'email' => 'instructor@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->organization->users()->attach($this->instructorUser->id, ['role' => 'instructor']);

        $this->instructor = Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->instructorUser->id,
            'activity_types' => ['paragliding', 'surfing'],
            'metadata' => [
                'can_tap_to_pay' => true,
                'stripe_terminal_location_id' => 'loc_test_123',
            ],
        ]);
    }

    public function test_login_returns_instructor_data(): void
    {
        // Définir l'organisation actuelle pour l'utilisateur
        $this->instructorUser->setCurrentOrganization($this->organization);
        session(['organization_id' => $this->organization->id]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'instructor@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'instructor' => [
                        'id',
                        'license_number',
                        'activity_types',
                        'can_tap_to_pay',
                    ],
                ],
            ]);

        $this->assertArrayHasKey('instructor', $response->json('data'));
        $this->assertEquals($this->instructor->id, $response->json('data.instructor.id'));
    }

    public function test_me_returns_instructor_data(): void
    {
        // Définir l'organisation dans la session pour que getCurrentOrganization() fonctionne
        $this->instructorUser->setCurrentOrganization($this->organization);
        session(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->instructorUser, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'instructor' => [
                        'id',
                        'license_number',
                        'experience_years',
                        'activity_types',
                        'availability',
                        'can_tap_to_pay',
                    ],
                ],
            ]);

        $this->assertArrayHasKey('instructor', $response->json('data'));
        $this->assertEquals($this->instructor->id, $response->json('data.instructor.id'));
        $this->assertContains('paragliding', $response->json('data.instructor.activity_types'));
    }

    public function test_me_returns_client_data_with_sessions(): void
    {
        $clientUser = User::factory()->create([
            'email' => 'client@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->organization->users()->attach($clientUser->id, ['role' => 'client']);

        $client = \App\Models\Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $clientUser->id,
            'total_flights' => 5,
        ]);

        // Définir l'organisation dans la session pour que getCurrentOrganization() fonctionne
        $clientUser->setCurrentOrganization($this->organization);
        session(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($clientUser, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'client' => [
                        'total_sessions',
                        'total_flights', // Rétrocompatibilité
                        'last_activity_date', // Nouveau
                        'last_flight_date', // Rétrocompatibilité
                    ],
                ],
            ]);

        $this->assertArrayHasKey('total_sessions', $response->json('data.client'));
        $this->assertArrayHasKey('total_flights', $response->json('data.client')); // Rétrocompatibilité
    }
}

