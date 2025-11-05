<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Reservation;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Test liste des rapports
     */
    public function test_admin_can_list_reports(): void
    {
        // Créer des données de test
        Reservation::factory()->count(5)->create(['created_at' => now()]);
        Payment::factory()->count(3)->create([
            'status' => 'succeeded',
            'captured_at' => now(),
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/reports?date_from=' . now()->subDays(7)->format('Y-m-d') . '&date_to=' . now()->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'date',
                        'reservations',
                        'scheduled',
                        'completed',
                        'cancelled',
                        'revenue',
                    ],
                ],
            ]);
    }

    /**
     * Test rapport quotidien
     */
    public function test_admin_can_get_daily_report(): void
    {
        $today = now();
        
        Reservation::factory()->count(3)->create(['created_at' => $today]);
        Reservation::factory()->count(2)->create([
            'scheduled_at' => $today,
            'status' => 'scheduled',
        ]);
        Reservation::factory()->create([
            'scheduled_at' => $today,
            'status' => 'completed',
        ]);
        Payment::factory()->create([
            'status' => 'succeeded',
            'captured_at' => $today,
            'amount' => 100.00,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/reports/daily?date=' . $today->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'date',
                    'stats' => [
                        'reservations',
                        'scheduled',
                        'completed',
                        'cancelled',
                        'revenue',
                    ],
                    'comparison' => [
                        'yesterday',
                        'revenue_evolution_percent',
                    ],
                ],
            ]);
    }

    /**
     * Test rapport mensuel
     */
    public function test_admin_can_get_monthly_report(): void
    {
        $thisMonth = now()->startOfMonth();
        
        Reservation::factory()->count(10)->create(['created_at' => $thisMonth]);
        Reservation::factory()->count(5)->create([
            'scheduled_at' => $thisMonth,
            'status' => 'completed',
        ]);
        Payment::factory()->count(5)->create([
            'status' => 'succeeded',
            'captured_at' => $thisMonth,
            'amount' => 150.00,
        ]);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/v1/admin/reports/monthly?year=' . now()->year . '&month=' . now()->month);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'month',
                    'year',
                    'total_reservations',
                    'scheduled',
                    'completed',
                    'cancelled',
                    'revenue',
                    'daily_breakdown' => [
                        '*' => [
                            'date',
                            'revenue',
                            'completed',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Test accès non autorisé (non admin)
     */
    public function test_non_admin_cannot_access_reports(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum');

        $response = $this->getJson('/api/v1/admin/reports');

        $response->assertStatus(403);
    }

    /**
     * Test accès non authentifié
     */
    public function test_unauthenticated_user_cannot_access_reports(): void
    {
        $response = $this->getJson('/api/v1/admin/reports');

        $response->assertStatus(401);
    }
}

