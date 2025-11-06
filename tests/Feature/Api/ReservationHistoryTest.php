<?php

namespace Tests\Feature\Api;

use App\Models\Reservation;
use App\Models\ReservationHistory;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $client;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Utiliser le driver array pour le cache dans les tests (plus rapide et pas besoin de table)
        config(['cache.default' => 'array']);
        \Illuminate\Support\Facades\Cache::flush();
        
        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->organization->users()->attach($this->admin->id, ['role' => 'admin', 'permissions' => ['*']]);
        $this->admin->setCurrentOrganization($this->organization);
        $this->client = User::factory()->create(['role' => 'client']);
        $this->organization->users()->attach($this->client->id, ['role' => 'client', 'permissions' => []]);
    }

    /**
     * Test admin peut voir l'historique d'une réservation
     */
    public function test_admin_can_view_reservation_history(): void
    {
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        
        ReservationHistory::factory()->count(3)->create([
            'reservation_id' => $reservation->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson("/api/v1/admin/reservations/{$reservation->id}/history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'action',
                        'old_values',
                        'new_values',
                        'user_id',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    /**
     * Test client peut voir l'historique de sa réservation
     */
    public function test_client_can_view_own_reservation_history(): void
    {
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->client->id,
        ]);
        
        ReservationHistory::factory()->count(2)->create([
            'reservation_id' => $reservation->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->getJson("/api/v1/my/reservations/{$reservation->id}/history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'action',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    /**
     * Test client ne peut pas voir l'historique d'une réservation qui ne lui appartient pas
     */
    public function test_client_cannot_view_other_reservation_history(): void
    {
        $otherClient = User::factory()->create(['role' => 'client']);
        // Créer une réservation avec un autre client et s'assurer qu'elle n'a aucun lien avec $this->client
        $reservation = Reservation::factory()->create([
            'user_id' => $otherClient->id,
            'client_id' => null, // S'assurer qu'il n'y a pas de client_id lié à $this->client
        ]);

        $this->actingAs($this->client, 'sanctum');

        $response = $this->getJson("/api/v1/my/reservations/{$reservation->id}/history");

        // La méthode retourne 404 si la réservation n'est pas trouvée pour cet utilisateur
        // Vérifier que ce n'est pas un succès (200)
        $this->assertNotEquals(200, $response->status());
        // Vérifier que c'est soit 404 soit 403
        $this->assertTrue(in_array($response->status(), [404, 403]));
    }

    /**
     * Test historique vide
     */
    public function test_returns_empty_history_when_no_history_exists(): void
    {
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson("/api/v1/admin/reservations/{$reservation->id}/history");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    /**
     * Test historique ordonné par date décroissante
     */
    public function test_history_is_ordered_by_date_descending(): void
    {
        $reservation = Reservation::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        
        $oldHistory = ReservationHistory::factory()->create([
            'reservation_id' => $reservation->id,
            'created_at' => now()->subDays(2),
        ]);
        
        $newHistory = ReservationHistory::factory()->create([
            'reservation_id' => $reservation->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson("/api/v1/admin/reservations/{$reservation->id}/history");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Le plus récent doit être en premier
        $this->assertEquals($newHistory->id, $data[0]['id']);
        $this->assertEquals($oldHistory->id, $data[1]['id']);
    }

    /**
     * Test accès non authentifié
     */
    public function test_unauthenticated_user_cannot_view_history(): void
    {
        $reservation = Reservation::factory()->create();

        $response = $this->getJson("/api/v1/admin/reservations/{$reservation->id}/history");

        $response->assertStatus(401);
    }
}

