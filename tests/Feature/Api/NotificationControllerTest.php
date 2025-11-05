<?php

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = User::factory()->create(['role' => 'client']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Test liste des notifications (authentifié)
     */
    public function test_authenticated_user_can_list_notifications(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->client->id,
            'recipient' => $this->client->email,
            'status' => 'sent',
        ]);

        $this->actingAs($this->client, 'sanctum');

        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'type',
                            'subject',
                            'status',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Test filtrage par type
     */
    public function test_can_filter_notifications_by_type(): void
    {
        Notification::factory()->create([
            'user_id' => $this->client->id,
            'type' => 'email',
            'status' => 'sent',
        ]);
        Notification::factory()->create([
            'user_id' => $this->client->id,
            'type' => 'sms',
            'status' => 'sent',
        ]);

        $this->actingAs($this->client, 'sanctum');

        $response = $this->getJson('/api/v1/notifications?type=email');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('email', $data[0]['type']);
    }

    /**
     * Test filtrage par réservation
     */
    public function test_can_filter_notifications_by_reservation(): void
    {
        $reservation = Reservation::factory()->create();
        
        Notification::factory()->create([
            'user_id' => $this->client->id,
            'reservation_id' => $reservation->id,
            'status' => 'sent',
        ]);
        Notification::factory()->create([
            'user_id' => $this->client->id,
            'status' => 'sent',
        ]);

        $this->actingAs($this->client, 'sanctum');

        $response = $this->getJson("/api/v1/notifications?reservation_id={$reservation->id}");

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals($reservation->id, $data[0]['reservation_id']);
    }

    /**
     * Test détails notification
     */
    public function test_can_view_notification_details(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->client->id,
            'recipient' => $this->client->email,
            'status' => 'sent',
        ]);

        $this->actingAs($this->client, 'sanctum');

        $response = $this->getJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'type',
                    'subject',
                    'content',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $notification->id,
                ],
            ]);
    }

    /**
     * Test marquer notification comme lue
     */
    public function test_can_mark_notification_as_read(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->client->id,
            'recipient' => $this->client->email,
            'status' => 'sent',
            'metadata' => [],
        ]);

        $this->actingAs($this->client, 'sanctum');

        $response = $this->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Notification marquée comme lue',
            ]);

        $notification->refresh();
        $this->assertArrayHasKey('read_at', $notification->metadata);
    }

    /**
     * Test marquer toutes les notifications comme lues
     */
    public function test_can_mark_all_notifications_as_read(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->client->id,
            'recipient' => $this->client->email,
            'status' => 'sent',
            'metadata' => [],
        ]);

        $this->actingAs($this->client, 'sanctum');

        $response = $this->postJson('/api/v1/notifications/mark-all-read');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'count',
            ]);

        $this->assertEquals(3, $response->json('count'));
    }

    /**
     * Test compter les notifications non lues
     */
    public function test_can_get_unread_count(): void
    {
        Notification::factory()->count(2)->create([
            'user_id' => $this->client->id,
            'recipient' => $this->client->email,
            'status' => 'sent',
            'metadata' => [],
        ]);
        Notification::factory()->create([
            'user_id' => $this->client->id,
            'recipient' => $this->client->email,
            'status' => 'sent',
            'metadata' => ['read_at' => now()->toISOString()],
        ]);

        $this->actingAs($this->client, 'sanctum');

        $response = $this->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'unread_count' => 2,
                ],
            ]);
    }

    /**
     * Test utilisateur ne peut pas voir les notifications d'autres utilisateurs
     */
    public function test_user_cannot_see_other_users_notifications(): void
    {
        $otherUser = User::factory()->create(['role' => 'client']);
        $notification = Notification::factory()->create([
            'user_id' => $otherUser->id,
            'recipient' => $otherUser->email,
            'status' => 'sent',
        ]);

        $this->actingAs($this->client, 'sanctum');

        $response = $this->getJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(404);
    }

    /**
     * Test accès non authentifié
     */
    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(401);
    }
}

