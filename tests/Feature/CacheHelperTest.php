<?php

namespace Tests\Feature;

use App\Helpers\CacheHelper;
use App\Models\Activity;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheHelperTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Organization $organization2;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Utiliser le driver array pour le cache dans les tests
        config(['cache.default' => 'array']);
        Cache::flush();
        
        $this->organization = Organization::factory()->create();
        $this->organization2 = Organization::factory()->create();
    }

    public function test_can_put_and_get_cache_value(): void
    {
        $key = 'test_key';
        $value = ['test' => 'data'];
        
        CacheHelper::put($this->organization->id, $key, $value, 3600);
        
        $cached = CacheHelper::get($this->organization->id, $key);
        
        $this->assertEquals($value, $cached);
    }

    public function test_cache_is_isolated_by_tenant(): void
    {
        $key = 'test_key';
        $value1 = ['org' => 1];
        $value2 = ['org' => 2];
        
        CacheHelper::put($this->organization->id, $key, $value1, 3600);
        CacheHelper::put($this->organization2->id, $key, $value2, 3600);
        
        $cached1 = CacheHelper::get($this->organization->id, $key);
        $cached2 = CacheHelper::get($this->organization2->id, $key);
        
        $this->assertEquals($value1, $cached1);
        $this->assertEquals($value2, $cached2);
        $this->assertNotEquals($cached1, $cached2);
    }

    public function test_remember_caches_callback_result(): void
    {
        $key = 'expensive_operation';
        $callCount = 0;
        
        $callback = function () use (&$callCount) {
            $callCount++;
            return ['result' => 'expensive_data'];
        };
        
        // Premier appel
        $result1 = CacheHelper::remember($this->organization->id, $key, 3600, $callback);
        
        // Deuxième appel (devrait utiliser le cache)
        $result2 = CacheHelper::remember($this->organization->id, $key, 3600, $callback);
        
        $this->assertEquals(['result' => 'expensive_data'], $result1);
        $this->assertEquals($result1, $result2);
        $this->assertEquals(1, $callCount, 'Callback should be called only once');
    }

    public function test_forget_removes_cache_value(): void
    {
        $key = 'test_key';
        $value = ['test' => 'data'];
        
        CacheHelper::put($this->organization->id, $key, $value, 3600);
        
        $this->assertNotNull(CacheHelper::get($this->organization->id, $key));
        
        CacheHelper::forget($this->organization->id, $key);
        
        $this->assertNull(CacheHelper::get($this->organization->id, $key));
    }

    public function test_activity_config_key_generation(): void
    {
        $activityId = 123;
        $key = CacheHelper::activityConfigKey($activityId);
        
        $this->assertStringContainsString('activity_config', $key);
        $this->assertStringContainsString((string) $activityId, $key);
    }

    public function test_module_config_key_generation(): void
    {
        $activityType = 'paragliding';
        $key = CacheHelper::moduleConfigKey($activityType);
        
        $this->assertStringContainsString('module_config', $key);
        $this->assertStringContainsString($activityType, $key);
    }

    public function test_activities_list_key_generation(): void
    {
        $filters = ['activity_type' => 'paragliding'];
        $key = CacheHelper::activitiesListKey($this->organization->id, $filters);
        
        $this->assertStringContainsString('activities_list', $key);
        $this->assertStringContainsString((string) $this->organization->id, $key);
        
        // Même filtres = même clé
        $key2 = CacheHelper::activitiesListKey($this->organization->id, $filters);
        $this->assertEquals($key, $key2);
        
        // Filtres différents = clés différentes
        $key3 = CacheHelper::activitiesListKey($this->organization->id, ['activity_type' => 'surfing']);
        $this->assertNotEquals($key, $key3);
    }

    public function test_instructors_list_key_generation(): void
    {
        $filters = ['activity_type' => 'paragliding'];
        $key = CacheHelper::instructorsListKey($this->organization->id, $filters);
        
        $this->assertStringContainsString('instructors_list', $key);
        $this->assertStringContainsString((string) $this->organization->id, $key);
    }

    public function test_stats_key_generation(): void
    {
        $params = ['period' => 'month'];
        $key = CacheHelper::statsKey($this->organization->id, 'summary', $params);
        
        $this->assertStringContainsString('stats', $key);
        $this->assertStringContainsString((string) $this->organization->id, $key);
        $this->assertStringContainsString('summary', $key);
    }

    public function test_invalidate_activity_clears_cache(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'constraints_config' => ['weight' => ['min' => 40]],
        ]);
        
        // Mettre en cache
        $cacheKey = CacheHelper::activityConfigKey($activity->id) . ':constraints';
        CacheHelper::put($this->organization->id, $cacheKey, $activity->constraints_config, 3600);
        
        $this->assertNotNull(CacheHelper::get($this->organization->id, $cacheKey));
        
        // Invalider
        CacheHelper::invalidateActivity($this->organization->id, $activity->id);
        
        // Le cache devrait être vidé
        $this->assertNull(CacheHelper::get($this->organization->id, $cacheKey));
    }

    public function test_invalidate_module_clears_cache(): void
    {
        $activityType = 'paragliding';
        $cacheKey = CacheHelper::moduleConfigKey($activityType);
        
        // Mettre en cache
        Cache::put($cacheKey, ['test' => 'data'], 3600);
        $this->assertNotNull(Cache::get($cacheKey));
        
        // Invalider
        CacheHelper::invalidateModule($activityType);
        
        // Le cache devrait être vidé
        $this->assertNull(Cache::get($cacheKey));
    }

    public function test_activity_cached_constraints_config(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'constraints_config' => [
                'weight' => ['min' => 40, 'max' => 120],
                'height' => ['min' => 140, 'max' => 250],
            ],
        ]);
        
        // Premier appel (pas de cache)
        $constraints1 = $activity->getCachedConstraintsConfig();
        
        // Modifier directement en base (sans passer par le modèle)
        \DB::table('activities')
            ->where('id', $activity->id)
            ->update(['constraints_config' => json_encode(['weight' => ['min' => 50]])]);
        
        // Deuxième appel (devrait retourner le cache)
        $constraints2 = $activity->getCachedConstraintsConfig();
        
        // Devrait être identique (cache non invalidé)
        $this->assertEquals($constraints1, $constraints2);
        
        // Invalider et récupérer
        CacheHelper::invalidateActivity($this->organization->id, $activity->id);
        $activity->refresh();
        $constraints3 = $activity->getCachedConstraintsConfig();
        
        // Devrait être différent (cache invalidé)
        $this->assertNotEquals($constraints1, $constraints3);
    }

    public function test_activity_cached_pricing_config(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'pricing_config' => [
                'base_price' => 100,
                'tandem' => 120,
            ],
        ]);
        
        $pricing = $activity->getCachedPricingConfig();
        
        $this->assertEquals(100, $pricing['base_price']);
        $this->assertEquals(120, $pricing['tandem']);
    }

    public function test_activity_cache_invalidation_on_update(): void
    {
        $activity = Activity::factory()->create([
            'organization_id' => $this->organization->id,
            'constraints_config' => ['weight' => ['min' => 40]],
        ]);
        
        // Mettre en cache
        $constraints1 = $activity->getCachedConstraintsConfig();
        $this->assertEquals(40, $constraints1['weight']['min']);
        
        // Invalider manuellement avant la mise à jour pour tester l'invalidation
        CacheHelper::invalidateActivity($this->organization->id, $activity->id);
        
        // Mettre à jour via le modèle
        $activity->update([
            'constraints_config' => ['weight' => ['min' => 50]],
        ]);
        
        // Récupérer à nouveau (devrait être différent car cache invalidé)
        $activity->refresh();
        $constraints2 = $activity->getCachedConstraintsConfig();
        
        // Le cache devrait être rechargé depuis la base avec la nouvelle valeur
        $this->assertEquals(50, $constraints2['weight']['min']);
        $this->assertNotEquals($constraints1['weight']['min'], $constraints2['weight']['min']);
    }
}

