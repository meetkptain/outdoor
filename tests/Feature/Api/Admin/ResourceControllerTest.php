<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Resource;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Test liste des ressources (admin)
     */
    public function test_admin_can_list_resources(): void
    {
        Resource::factory()->count(5)->create();

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/resources');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'code',
                            'name',
                            'type',
                            'is_active',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Test filtrage par type
     */
    public function test_admin_can_filter_resources_by_type(): void
    {
        Resource::factory()->count(3)->vehicle()->create();
        Resource::factory()->count(2)->tandemGlider()->create();

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/resources?type=vehicle');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(3, $data);
        $this->assertTrue(collect($data)->every(fn ($item) => $item['type'] === 'vehicle'));
    }

    /**
     * Test filtrage par statut actif
     */
    public function test_admin_can_filter_resources_by_active_status(): void
    {
        Resource::factory()->count(3)->create(['is_active' => true]);
        Resource::factory()->count(2)->create(['is_active' => false]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/resources?is_active=true');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertTrue(collect($data)->every(fn ($item) => $item['is_active'] === true));
    }

    /**
     * Test recherche par nom ou code
     */
    public function test_admin_can_search_resources(): void
    {
        Resource::factory()->create(['name' => 'Navette Test', 'code' => 'NAV001']);
        Resource::factory()->create(['name' => 'Autre ressource', 'code' => 'RES002']);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/resources?search=Test');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Navette Test', $data[0]['name']);
    }

    /**
     * Test liste des navettes
     */
    public function test_admin_can_list_vehicles(): void
    {
        Resource::factory()->count(3)->vehicle()->create();
        Resource::factory()->count(2)->tandemGlider()->create();

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/resources/vehicles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'name',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertTrue(collect($data)->every(fn ($item) => $item['type'] === 'vehicle'));
    }

    /**
     * Test liste des tandem gliders
     */
    public function test_admin_can_list_tandem_gliders(): void
    {
        Resource::factory()->count(2)->vehicle()->create();
        Resource::factory()->count(4)->tandemGlider()->create();

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/resources/tandem-gliders');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(4, $data);
        $this->assertTrue(collect($data)->every(fn ($item) => $item['type'] === 'tandem_glider'));
    }

    /**
     * Test ressources disponibles
     */
    public function test_admin_can_get_available_resources(): void
    {
        $vehicle = Resource::factory()->vehicle()->create(['is_active' => true]);
        Resource::factory()->vehicle()->create(['is_active' => false]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/resources/available?type=vehicle&date=' . now()->addDay()->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                    ],
                ],
            ]);

        // Vérifier que la réponse contient au moins le véhicule actif
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $vehicleIds = collect($data)->pluck('id')->toArray();
        $this->assertContains($vehicle->id, $vehicleIds);
        $this->assertTrue(collect($data)->every(fn ($item) => $item['type'] === 'vehicle'));
    }

    /**
     * Test création ressource
     */
    public function test_admin_can_create_resource(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $data = [
            'code' => 'NAV001',
            'name' => 'Navette Test',
            'type' => 'vehicle',
            'description' => 'Description test',
            'specifications' => [
                'capacity' => 8,
                'weight_limit' => 450,
            ],
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/admin/resources', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'code',
                    'name',
                    'type',
                ],
            ]);

        $this->assertDatabaseHas('resources', [
            'code' => 'NAV001',
            'name' => 'Navette Test',
            'type' => 'vehicle',
        ]);
    }

    /**
     * Test validation création ressource
     */
    public function test_admin_cannot_create_resource_with_invalid_data(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/v1/admin/resources', [
            'code' => '', // Code requis
            'type' => 'invalid_type', // Type invalide
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name', 'type']);
    }

    /**
     * Test détails ressource
     */
    public function test_admin_can_get_resource_details(): void
    {
        $resource = Resource::factory()->vehicle()->create();

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson("/api/v1/admin/resources/{$resource->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'code',
                    'name',
                    'type',
                    'specifications',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $resource->id,
                    'code' => $resource->code,
                ],
            ]);
    }

    /**
     * Test modification ressource
     */
    public function test_admin_can_update_resource(): void
    {
        $resource = Resource::factory()->vehicle()->create();

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->putJson("/api/v1/admin/resources/{$resource->id}", [
            'name' => 'Navette Modifiée',
            'description' => 'Nouvelle description',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ressource mise à jour avec succès',
            ]);

        $this->assertDatabaseHas('resources', [
            'id' => $resource->id,
            'name' => 'Navette Modifiée',
        ]);
    }

    /**
     * Test suppression ressource (sans réservations)
     */
    public function test_admin_can_delete_resource_without_reservations(): void
    {
        $resource = Resource::factory()->vehicle()->create();

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->deleteJson("/api/v1/admin/resources/{$resource->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ressource supprimée avec succès',
            ]);

        $this->assertDatabaseMissing('resources', ['id' => $resource->id]);
    }

    /**
     * Test suppression ressource (avec réservations) - soft delete
     */
    public function test_admin_cannot_delete_resource_with_reservations(): void
    {
        $vehicle = Resource::factory()->vehicle()->create();
        $reservation = Reservation::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->deleteJson("/api/v1/admin/resources/{$vehicle->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ressource désactivée (utilisée dans des réservations)',
            ]);

        $this->assertDatabaseHas('resources', [
            'id' => $vehicle->id,
            'is_active' => false,
        ]);
    }

    /**
     * Test accès non autorisé (non admin)
     */
    public function test_non_admin_cannot_access_resources(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum');

        $response = $this->getJson('/api/v1/admin/resources');

        $response->assertStatus(403);
    }
}

