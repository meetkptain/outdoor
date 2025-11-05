<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientControllerGeneralizedTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $admin;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        
        $this->admin = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $this->organization->users()->attach($this->admin->id, ['role' => 'admin']);

        $clientUser = User::factory()->create();
        $this->client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $clientUser->id,
            'total_flights' => 5,
        ]);
    }

    public function test_client_show_includes_total_sessions(): void
    {
        // Définir l'organisation dans la session
        $this->admin->setCurrentOrganization($this->organization);
        session(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->getJson("/api/v1/clients/{$this->client->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_sessions',
                    'total_flights', // Rétrocompatibilité
                    'last_activity_date', // Nouveau
                    'last_flight_date', // Rétrocompatibilité
                ],
            ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('total_sessions', $data);
        $this->assertArrayHasKey('total_flights', $data); // Rétrocompatibilité
        $this->assertEquals($this->client->total_flights, $data['total_sessions']);
    }
}

