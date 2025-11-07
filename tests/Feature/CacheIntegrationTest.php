<?php

namespace Tests\Feature;

use App\Helpers\CacheHelper;
use App\Models\Activity;
use App\Models\Instructor;
use App\Models\Organization;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected DashboardService $dashboardService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Utiliser le driver array pour le cache dans les tests
        config(['cache.default' => 'array']);
        Cache::flush();
        
        $this->organization = Organization::factory()->create();
        $this->dashboardService = new DashboardService();
    }

    public function test_activities_list_is_cached(): void
    {
        // Créer des activités
        Activity::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        
        $filters = [];
        $cacheKey = CacheHelper::activitiesListKey($this->organization->id, $filters);
        
        // Premier appel (pas de cache)
        $activities1 = CacheHelper::remember(
            $this->organization->id,
            $cacheKey,
            300,
            fn() => Activity::where('organization_id', $this->organization->id)
                ->where('is_active', true)
                ->get()
        );
        
        $initialCount = $activities1->count();
        $this->assertEquals(3, $initialCount);
        
        // Créer une nouvelle activité
        // Note: Activity::created() invalide automatiquement le cache via booted()
        Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        
        // Deuxième appel
        // Le cache devrait être invalidé par Activity::created(), donc on devrait avoir la nouvelle valeur
        $activities2 = CacheHelper::remember(
            $this->organization->id,
            $cacheKey,
            300,
            fn() => Activity::where('organization_id', $this->organization->id)
                ->where('is_active', true)
                ->get()
        );
        
        // Le cache devrait être invalidé, donc on devrait avoir +1 activité
        $this->assertGreaterThan($initialCount, $activities2->count());
        $this->assertEquals(4, $activities2->count());
        
        // Invalider manuellement et récupérer (pour tester l'invalidation manuelle)
        CacheHelper::invalidateActivitiesList($this->organization->id);
        
        // Créer une autre activité après invalidation manuelle
        Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        
        $activities3 = CacheHelper::remember(
            $this->organization->id,
            $cacheKey,
            300,
            fn() => Activity::where('organization_id', $this->organization->id)
                ->where('is_active', true)
                ->get()
        );
        
        // Devrait avoir encore +1 (5 au total)
        $this->assertGreaterThan($activities2->count(), $activities3->count());
        $this->assertEquals(5, $activities3->count());
    }

    public function test_instructors_list_is_cached(): void
    {
        $user = User::factory()->create();
        
        // Créer des instructeurs
        Instructor::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        
        $filters = [];
        $cacheKey = CacheHelper::instructorsListKey($this->organization->id, $filters);
        
        // Premier appel
        $instructors1 = CacheHelper::remember(
            $this->organization->id,
            $cacheKey,
            300,
            fn() => Instructor::where('organization_id', $this->organization->id)
                ->where('is_active', true)
                ->get()
        );
        
        // Créer un nouvel instructeur (sans invalider le cache)
        Instructor::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        
        // Deuxième appel (devrait retourner le cache)
        $instructors2 = CacheHelper::remember(
            $this->organization->id,
            $cacheKey,
            300,
            fn() => Instructor::where('organization_id', $this->organization->id)
                ->where('is_active', true)
                ->get()
        );
        
        // Devrait être identique (cache non invalidé)
        $this->assertEquals($instructors1->count(), $instructors2->count());
    }

    public function test_dashboard_stats_are_cached(): void
    {
        $cacheKey = CacheHelper::statsKey($this->organization->id, 'summary', ['period' => 'month']);
        
        // Premier appel
        $summary1 = $this->dashboardService->getSummary('month', $this->organization->id);
        
        // Vérifier que c'est en cache
        $cached = CacheHelper::get($this->organization->id, $cacheKey);
        $this->assertNotNull($cached);
        
        // Deuxième appel (devrait utiliser le cache)
        $summary2 = $this->dashboardService->getSummary('month', $this->organization->id);
        
        // Devrait être identique
        $this->assertEquals($summary1, $summary2);
    }

    public function test_dashboard_stats_cache_is_isolated_by_organization(): void
    {
        $organization2 = Organization::factory()->create();
        
        $summary1 = $this->dashboardService->getSummary('month', $this->organization->id);
        $summary2 = $this->dashboardService->getSummary('month', $organization2->id);
        
        // Les deux organisations devraient avoir leurs propres caches
        $this->assertNotNull($summary1);
        $this->assertNotNull($summary2);
        
        // Les clés de cache devraient être différentes
        $key1 = CacheHelper::statsKey($this->organization->id, 'summary', ['period' => 'month']);
        $key2 = CacheHelper::statsKey($organization2->id, 'summary', ['period' => 'month']);
        
        $this->assertNotEquals($key1, $key2);
    }

    public function test_cache_ttl_expiration(): void
    {
        $key = 'test_ttl';
        $value = ['test' => 'data'];
        
        // Mettre en cache avec TTL très court (1 seconde)
        CacheHelper::put($this->organization->id, $key, $value, 1);
        
        // Vérifier que c'est en cache
        $this->assertNotNull(CacheHelper::get($this->organization->id, $key));
        
        // Attendre que le TTL expire
        sleep(2);
        
        // Devrait être expiré
        $this->assertNull(CacheHelper::get($this->organization->id, $key));
    }
}

