<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Reservation;
use App\Models\Payment;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
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
    }

    /**
     * Test liste des rapports
     */
    public function test_admin_can_list_reports(): void
    {
        // Créer des données de test
        Reservation::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'created_at' => now(),
        ]);
        Payment::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'status' => 'succeeded',
            'captured_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson('/api/v1/admin/reports?date_from=' . now()->subDays(7)->format('Y-m-d') . '&date_to=' . now()->format('Y-m-d'));

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
        
        Reservation::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'created_at' => $today,
        ]);
        Reservation::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'scheduled_at' => $today,
            'status' => 'scheduled',
        ]);
        Reservation::factory()->create([
            'organization_id' => $this->organization->id,
            'scheduled_at' => $today,
            'status' => 'completed',
        ]);
        Payment::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'succeeded',
            'captured_at' => $today,
            'amount' => 100.00,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson('/api/v1/admin/reports/daily?date=' . $today->format('Y-m-d'));

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
        
        Reservation::factory()->count(10)->create([
            'organization_id' => $this->organization->id,
            'created_at' => $thisMonth,
        ]);
        Reservation::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'scheduled_at' => $thisMonth,
            'status' => 'completed',
        ]);
        Payment::factory()->count(5)->create([
            'organization_id' => $this->organization->id,
            'status' => 'succeeded',
            'captured_at' => $thisMonth,
            'amount' => 150.00,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->withSession(['organization_id' => $this->organization->id])
            ->withHeaders(['X-Organization-ID' => $this->organization->id])
            ->getJson('/api/v1/admin/reports/monthly?year=' . now()->year . '&month=' . now()->month);

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

