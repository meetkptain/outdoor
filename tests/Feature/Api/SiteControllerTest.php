<?php

namespace Tests\Feature\Api;

use App\Models\Site;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->client = User::factory()->create(['role' => 'client']);
    }

    /**
     * Test liste des sites (public)
     */
    public function test_public_can_list_sites(): void
    {
        Site::factory()->count(5)->create(['is_active' => true]);
        Site::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/sites');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'code',
                            'name',
                            'location',
                            'is_active',
                        ],
                    ],
                ],
            ]);

        // Public ne voit que les sites actifs
        $data = $response->json('data.data');
        $this->assertCount(5, $data);
        $this->assertTrue(collect($data)->every(fn ($item) => $item['is_active'] === true));
    }

    /**
     * Test admin peut voir tous les sites
     */
    public function test_admin_can_see_all_sites(): void
    {
        Site::factory()->count(3)->create(['is_active' => true]);
        Site::factory()->count(2)->create(['is_active' => false]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/sites?is_active=false');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(2, $data);
    }

    /**
     * Test filtrage par difficulté
     */
    public function test_can_filter_sites_by_difficulty(): void
    {
        Site::factory()->count(3)->create(['difficulty_level' => 'beginner', 'is_active' => true]);
        Site::factory()->count(2)->create(['difficulty_level' => 'advanced', 'is_active' => true]);

        $response = $this->getJson('/api/v1/sites?difficulty_level=beginner');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(3, $data);
        $this->assertTrue(collect($data)->every(fn ($item) => $item['difficulty_level'] === 'beginner'));
    }

    /**
     * Test recherche par nom ou code
     */
    public function test_can_search_sites(): void
    {
        Site::factory()->create(['name' => 'Site Test', 'code' => 'SITE001', 'is_active' => true]);
        Site::factory()->create(['name' => 'Autre site', 'code' => 'SITE002', 'is_active' => true]);

        $response = $this->getJson('/api/v1/sites?search=Test');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Site Test', $data[0]['name']);
    }

    /**
     * Test détails site (public)
     */
    public function test_public_can_view_active_site(): void
    {
        $site = Site::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/sites/{$site->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'code',
                    'name',
                    'location',
                    'latitude',
                    'longitude',
                    'altitude',
                    'difficulty_level',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $site->id,
                    'code' => $site->code,
                ],
            ]);
    }

    /**
     * Test public ne peut pas voir site inactif
     */
    public function test_public_cannot_view_inactive_site(): void
    {
        $site = Site::factory()->create(['is_active' => false]);

        $response = $this->getJson("/api/v1/sites/{$site->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Site non disponible',
            ]);
    }

    /**
     * Test admin peut voir site inactif
     */
    public function test_admin_can_view_inactive_site(): void
    {
        $site = Site::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson("/api/v1/sites/{$site->id}");

        $response->assertStatus(200);
    }

    /**
     * Test création site (admin)
     */
    public function test_admin_can_create_site(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $data = [
            'code' => 'SITE001',
            'name' => 'Site de Test',
            'description' => 'Description test',
            'location' => 'Paris, France',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'altitude' => 1000,
            'difficulty_level' => 'beginner',
            'orientation' => 'N',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/sites', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'code',
                    'name',
                    'location',
                ],
            ]);

        $this->assertDatabaseHas('sites', [
            'code' => 'SITE001',
            'name' => 'Site de Test',
        ]);
    }

    /**
     * Test validation création site
     */
    public function test_admin_cannot_create_site_with_invalid_data(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/v1/sites', [
            'code' => '', // Code requis
            'name' => '', // Name requis
            'latitude' => 100, // Latitude invalide (> 90)
            'difficulty_level' => 'invalid', // Difficulté invalide
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name', 'latitude', 'location', 'longitude', 'altitude', 'difficulty_level']);
    }

    /**
     * Test modification site (admin)
     */
    public function test_admin_can_update_site(): void
    {
        $site = Site::factory()->create();

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->putJson("/api/v1/sites/{$site->id}", [
            'name' => 'Site Modifié',
            'description' => 'Nouvelle description',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Site mis à jour avec succès',
            ]);

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'name' => 'Site Modifié',
        ]);
    }

    /**
     * Test suppression site (sans réservations)
     */
    public function test_admin_can_delete_site_without_reservations(): void
    {
        $site = Site::factory()->create();

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->deleteJson("/api/v1/sites/{$site->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Site supprimé avec succès',
            ]);

        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }

    /**
     * Test suppression site (avec réservations) - soft delete
     */
    public function test_admin_cannot_delete_site_with_reservations(): void
    {
        $site = Site::factory()->create();
        $reservation = Reservation::factory()->create(['site_id' => $site->id]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->deleteJson("/api/v1/sites/{$site->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Site désactivé (utilisé dans des réservations)',
            ]);

        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'is_active' => false,
        ]);
    }

    /**
     * Test accès non autorisé (non admin)
     */
    public function test_non_admin_cannot_create_site(): void
    {
        $this->actingAs($this->client, 'sanctum');

        $response = $this->postJson('/api/v1/sites', [
            'code' => 'SITE001',
            'name' => 'Test',
            'location' => 'Paris',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'altitude' => 1000,
            'difficulty_level' => 'beginner',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test accès non autorisé modification
     */
    public function test_non_admin_cannot_update_site(): void
    {
        $site = Site::factory()->create();

        $this->actingAs($this->client, 'sanctum');

        $response = $this->putJson("/api/v1/sites/{$site->id}", [
            'name' => 'Test',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test accès non autorisé suppression
     */
    public function test_non_admin_cannot_delete_site(): void
    {
        $site = Site::factory()->create();

        $this->actingAs($this->client, 'sanctum');

        $response = $this->deleteJson("/api/v1/sites/{$site->id}");

        $response->assertStatus(403);
    }
}

