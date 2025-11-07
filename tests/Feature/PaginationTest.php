<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['cache.default' => 'array']);
        Cache::flush();
        
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();
        $this->user->organizations()->attach($this->organization->id, ['role' => 'client']);
    }

    public function test_reservations_pagination_format(): void
    {
        // Créer un client pour l'utilisateur
        $client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
        ]);

        // Créer des réservations
        Reservation::factory()->count(25)->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        // Authentifier l'utilisateur
        $response = $this->actingAs($this->user)
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/my/reservations?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'from',
                    'to',
                    'has_more_pages',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(1, $response->json('pagination.current_page'));
        $this->assertEquals(10, $response->json('pagination.per_page'));
        $this->assertEquals(25, $response->json('pagination.total'));
        $this->assertEquals(3, $response->json('pagination.last_page'));
        $this->assertCount(10, $response->json('data'));
    }

    public function test_pagination_parameters_validation(): void
    {
        $client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
        ]);

        Reservation::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        // Page négative → corrigée à 1
        $response = $this->actingAs($this->user)
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/my/reservations?page=-1&per_page=10');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.current_page'));

        // per_page trop grand → limité à 100
        $response = $this->actingAs($this->user)
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/my/reservations?page=1&per_page=200');

        $response->assertStatus(200);
        $this->assertEquals(100, $response->json('pagination.per_page'));

        // per_page négatif → corrigé à 1
        $response = $this->actingAs($this->user)
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/my/reservations?page=1&per_page=-5');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.per_page'));
    }

    public function test_pagination_last_page(): void
    {
        $client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
        ]);

        Reservation::factory()->count(30)->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        // Dernière page
        $response = $this->actingAs($this->user)
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/my/reservations?page=3&per_page=15');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('pagination.current_page'));
        $this->assertEquals(2, $response->json('pagination.last_page'));
        $this->assertFalse($response->json('pagination.has_more_pages'));
    }

    public function test_pagination_empty_results(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/my/reservations?page=1&per_page=10');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEmpty($response->json('data'));
        $this->assertEquals(0, $response->json('pagination.total'));
        $this->assertEquals(1, $response->json('pagination.last_page'));
        $this->assertNull($response->json('pagination.from'));
        $this->assertNull($response->json('pagination.to'));
    }

    public function test_pagination_default_values(): void
    {
        $client = Client::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
        ]);

        Reservation::factory()->count(20)->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        // Sans paramètres → valeurs par défaut
        $response = $this->actingAs($this->user)
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/my/reservations');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.current_page'));
        $this->assertEquals(15, $response->json('pagination.per_page'));
    }

    public function test_admin_reservations_pagination(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->organizations()->attach($this->organization->id, ['role' => 'admin']);

        Reservation::factory()->count(20)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('X-Organization-ID', $this->organization->id)
            ->getJson('/api/v1/admin/reservations?page=2&per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ]);

        $this->assertEquals(2, $response->json('pagination.current_page'));
        $this->assertEquals(5, $response->json('pagination.per_page'));
        $this->assertCount(5, $response->json('data'));
    }
}

