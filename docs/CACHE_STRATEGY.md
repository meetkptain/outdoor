# 🚀 Stratégie de Cache Multi-Tenant

**Date de création:** 2025-11-06  
**Version:** 1.0.0  
**Objectif:** Optimiser les performances avec cache isolé par tenant

---

## 🎯 Vue d'Ensemble

Le système de cache est conçu pour améliorer les performances tout en maintenant l'isolation des données entre les tenants (organisations). Chaque organisation a son propre espace de cache isolé.

---

## 📋 Architecture

### CacheHelper

Le helper `CacheHelper` centralise toute la logique de cache multi-tenant :

```php
use App\Helpers\CacheHelper;

// Mettre en cache
CacheHelper::put($organizationId, 'key', $value, 3600); // TTL 1 heure

// Récupérer avec cache
$value = CacheHelper::get($organizationId, 'key');

// Remember pattern
$value = CacheHelper::remember($organizationId, 'key', 300, function() {
    return expensiveOperation();
});
```

### Structure des Clés

Toutes les clés de cache suivent le pattern :
```
tenant:org:{organization_id}:{type}:{identifier}
```

Exemples :
- `tenant:org:1:activity_config:5:constraints`
- `tenant:org:1:activities_list:filters:abc123`
- `tenant:org:1:stats:type:summary:params:def456`

---

## 🔧 Configurations Mises en Cache

### 1. Configurations d'Activités

#### Contraintes (`constraints_config`)
- **Clé** : `activity_config:{activity_id}:constraints`
- **TTL** : 1 heure (3600 secondes)
- **Invalidation** : Automatique lors de `Activity::updated()`

```php
$activity = Activity::find(1);
$constraints = $activity->getCachedConstraintsConfig();
```

#### Prix (`pricing_config`)
- **Clé** : `activity_config:{activity_id}:pricing`
- **TTL** : 1 heure (3600 secondes)
- **Invalidation** : Automatique lors de `Activity::updated()`

```php
$activity = Activity::find(1);
$pricing = $activity->getCachedPricingConfig();
```

### 2. Configurations de Modules

- **Clé** : `module_config:{activity_type}`
- **TTL** : 1 heure (3600 secondes)
- **Invalidation** : Lors de l'enregistrement d'un module

```php
$moduleRegistry = app(ModuleRegistry::class);
$config = $moduleRegistry->getCachedConfig('paragliding');
```

---

## 📊 Requêtes Fréquentes Mises en Cache

### 1. Liste des Activités

- **Endpoint** : `GET /api/v1/activities`
- **Clé** : `activities_list:org:{org_id}:filters:{hash}`
- **TTL** : 5 minutes (300 secondes)
- **Invalidation** : Automatique lors de création/mise à jour/suppression d'activité

```php
// Dans ActivityController
$activities = CacheHelper::remember(
    $organizationId,
    CacheHelper::activitiesListKey($organizationId, $filters),
    300,
    fn() => Activity::where('organization_id', $organizationId)->get()
);
```

### 2. Liste des Instructeurs

- **Endpoint** : `GET /api/v1/instructors`
- **Clé** : `instructors_list:org:{org_id}:filters:{hash}`
- **TTL** : 5 minutes (300 secondes)
- **Invalidation** : Manuelle (à implémenter avec observer)

```php
// Dans InstructorController
$instructors = CacheHelper::remember(
    $organizationId,
    CacheHelper::instructorsListKey($organizationId, $filters),
    300,
    fn() => Instructor::where('is_active', true)->get()
);
```

### 3. Statistiques Dashboard

- **Endpoints** : 
  - `GET /api/v1/admin/dashboard/summary`
  - `GET /api/v1/admin/dashboard/stats`
- **Clé** : `stats:org:{org_id}:type:{type}:params:{hash}`
- **TTL** : 5 minutes (300 secondes)
- **Invalidation** : Manuelle (lors de création/modification de réservation)

```php
// Dans DashboardService
$summary = CacheHelper::remember(
    $organizationId,
    CacheHelper::statsKey($organizationId, 'summary', ['period' => $period]),
    300,
    fn() => $this->calculateSummary($period)
);
```

### 4. Sites Disponibles

- **Endpoint** : `GET /api/v1/sites`
- **Clé** : `sites:org:{org_id}`
- **TTL** : 5 minutes (300 secondes)
- **Invalidation** : Automatique (à implémenter avec observer)

---

## 🔄 Invalidation Automatique

### Observers dans les Modèles

Les modèles invalident automatiquement leur cache via les événements Eloquent :

```php
// Dans Activity::booted()
static::updated(function ($activity) {
    CacheHelper::invalidateActivity($activity->organization_id, $activity->id);
    CacheHelper::invalidateActivitiesList($activity->organization_id);
});
```

### Méthodes d'Invalidation

```php
// Invalider une activité spécifique
CacheHelper::invalidateActivity($organizationId, $activityId);

// Invalider toutes les listes d'activités
CacheHelper::invalidateActivitiesList($organizationId);

// Invalider toutes les listes d'instructeurs
CacheHelper::invalidateInstructorsList($organizationId);

// Invalider les statistiques
CacheHelper::invalidateStats($organizationId);

// Invalider un module
CacheHelper::invalidateModule($activityType);

// Vider tout le cache d'un tenant
CacheHelper::clearTenant($organizationId);
```

---

## 🛠️ Commande Artisan

### Vider le Cache d'un Tenant

```bash
# Vider le cache d'une organisation spécifique
php artisan cache:clear-tenant {organization_id}

# Vider le cache de toutes les organisations
php artisan cache:clear-tenant --all
```

Exemple :
```bash
php artisan cache:clear-tenant 1
```

---

## ⚙️ Configuration

### Driver de Cache

Par défaut, le système utilise le driver configuré dans `config/cache.php`. Pour une meilleure performance en production :

1. **Redis avec Tags** (recommandé)
   ```env
   CACHE_DRIVER=redis
   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379
   ```

2. **Array** (pour les tests)
   ```env
   CACHE_DRIVER=array
   ```

### TTL Recommandés

| Type de Données | TTL | Raison |
|----------------|-----|--------|
| Configurations d'activités | 1 heure | Changements peu fréquents |
| Configurations de modules | 1 heure | Changements très rares |
| Listes d'activités | 5 minutes | Données relativement statiques |
| Listes d'instructeurs | 5 minutes | Changements modérés |
| Statistiques dashboard | 5 minutes | Données qui changent fréquemment |
| Sites | 5 minutes | Données relativement statiques |

---

## 📝 Bonnes Pratiques

### 1. Toujours Utiliser CacheHelper

❌ **Mauvais** :
```php
Cache::put('key', $value);
```

✅ **Bon** :
```php
CacheHelper::put($organizationId, 'key', $value, 3600);
```

### 2. Invalider lors des Modifications

Toujours invalider le cache lors de la création, mise à jour ou suppression :

```php
// Dans un observer ou dans le modèle
Activity::created(function ($activity) {
    CacheHelper::invalidateActivitiesList($activity->organization_id);
});
```

### 3. Utiliser Remember Pattern

Pour éviter les requêtes répétées :

```php
$data = CacheHelper::remember(
    $organizationId,
    'key',
    300,
    fn() => expensiveDatabaseQuery()
);
```

### 4. Tester avec Array Driver

Dans les tests, utiliser le driver `array` pour éviter les dépendances Redis :

```php
// Dans TestCase::setUp()
config(['cache.default' => 'array']);
Cache::flush();
```

---

## 🧪 Tests

### Exemple de Test

```php
public function test_activity_config_is_cached(): void
{
    $organization = Organization::factory()->create();
    $activity = Activity::factory()->create([
        'organization_id' => $organization->id,
    ]);

    // Premier appel (pas de cache)
    $constraints1 = $activity->getCachedConstraintsConfig();
    
    // Modifier directement en base
    $activity->update(['constraints_config' => ['weight' => ['min' => 50]]]);
    
    // Deuxième appel (devrait retourner le cache)
    $constraints2 = $activity->getCachedConstraintsConfig();
    
    // Devrait être identique (cache non invalidé)
    $this->assertEquals($constraints1, $constraints2);
    
    // Invalider et récupérer
    CacheHelper::invalidateActivity($organization->id, $activity->id);
    $constraints3 = $activity->getCachedConstraintsConfig();
    
    // Devrait être différent (cache invalidé)
    $this->assertNotEquals($constraints1, $constraints3);
}
```

---

## 🚨 Limitations Actuelles

1. **Suppression par Pattern** : La suppression par pattern (ex: `tenant:org:1:*`) nécessite Redis avec tags. Sans Redis, `clearTenant()` retourne 0.

2. **Cache Global** : Les configurations de modules sont globales (pas par tenant) car elles sont partagées entre toutes les organisations.

3. **Invalidation Partielle** : Pour les listes avec filtres, on invalide seulement la clé principale. Les variantes avec filtres restent en cache jusqu'à expiration.

---

## 🔮 Améliorations Futures

1. **Redis Tags** : Implémenter la suppression par tags pour une meilleure gestion
2. **Cache Warming** : Précharger le cache au démarrage
3. **Cache Metrics** : Suivre les taux de hit/miss
4. **Cache Layers** : Implémenter plusieurs niveaux de cache (L1: mémoire, L2: Redis)

---

## 📚 Références

- **Helper** : `app/Helpers/CacheHelper.php`
- **Modèle Activity** : `app/Models/Activity.php`
- **ModuleRegistry** : `app/Modules/ModuleRegistry.php`
- **Commande** : `app/Console/Commands/ClearTenantCache.php`

---

**Date de création:** 2025-11-06  
**Dernière mise à jour:** 2025-11-06  
**Créé par:** Auto (IA Assistant)

