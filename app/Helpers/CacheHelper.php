<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Helper pour la gestion du cache multi-tenant
 * 
 * Permet d'isoler le cache par organisation (tenant) et de gérer
 * l'invalidation automatique lors des modifications.
 */
class CacheHelper
{
    /**
     * Préfixe pour les clés de cache par tenant
     */
    protected const TENANT_PREFIX = 'tenant';

    /**
     * Préfixe pour les configurations d'activités
     */
    protected const ACTIVITY_CONFIG_PREFIX = 'activity_config';

    /**
     * Préfixe pour les configurations de modules
     */
    protected const MODULE_CONFIG_PREFIX = 'module_config';

    /**
     * Préfixe pour les listes d'activités
     */
    protected const ACTIVITIES_LIST_PREFIX = 'activities_list';

    /**
     * Préfixe pour les listes d'instructeurs
     */
    protected const INSTRUCTORS_LIST_PREFIX = 'instructors_list';

    /**
     * Préfixe pour les statistiques
     */
    protected const STATS_PREFIX = 'stats';

    /**
     * Préfixe pour les sites
     */
    protected const SITES_PREFIX = 'sites';

    /**
     * Générer une clé de cache pour un tenant
     * 
     * @param int $organizationId ID de l'organisation
     * @param string $key Clé de cache
     * @return string Clé complète avec préfixe tenant
     */
    public static function tenantKey(int $organizationId, string $key): string
    {
        return self::TENANT_PREFIX . ':org:' . $organizationId . ':' . $key;
    }

    /**
     * Récupérer une valeur du cache pour un tenant
     * 
     * @param int $organizationId ID de l'organisation
     * @param string $key Clé de cache
     * @param mixed $default Valeur par défaut
     * @return mixed
     */
    public static function get(int $organizationId, string $key, $default = null)
    {
        $cacheKey = self::tenantKey($organizationId, $key);
        return Cache::get($cacheKey, $default);
    }

    /**
     * Mettre une valeur en cache pour un tenant
     * 
     * @param int $organizationId ID de l'organisation
     * @param string $key Clé de cache
     * @param mixed $value Valeur à mettre en cache
     * @param int|null $ttl Durée de vie en secondes (null = pas d'expiration)
     * @return bool
     */
    public static function put(int $organizationId, string $key, $value, ?int $ttl = null): bool
    {
        $cacheKey = self::tenantKey($organizationId, $key);
        
        if ($ttl !== null) {
            return Cache::put($cacheKey, $value, $ttl);
        }
        
        return Cache::forever($cacheKey, $value);
    }

    /**
     * Récupérer ou mettre en cache une valeur (cache remember)
     * 
     * @param int $organizationId ID de l'organisation
     * @param string $key Clé de cache
     * @param int|null $ttl Durée de vie en secondes
     * @param callable $callback Fonction pour générer la valeur si absente
     * @return mixed
     */
    public static function remember(int $organizationId, string $key, ?int $ttl, callable $callback)
    {
        $cacheKey = self::tenantKey($organizationId, $key);
        
        if ($ttl !== null) {
            return Cache::remember($cacheKey, $ttl, $callback);
        }
        
        return Cache::rememberForever($cacheKey, $callback);
    }

    /**
     * Supprimer une clé de cache pour un tenant
     * 
     * @param int $organizationId ID de l'organisation
     * @param string $key Clé de cache
     * @return bool
     */
    public static function forget(int $organizationId, string $key): bool
    {
        $cacheKey = self::tenantKey($organizationId, $key);
        return Cache::forget($cacheKey);
    }

    /**
     * Supprimer tout le cache d'un tenant
     * 
     * @param int $organizationId ID de l'organisation
     * @return int Nombre de clés supprimées
     */
    public static function clearTenant(int $organizationId): int
    {
        // Note: Laravel Cache ne supporte pas directement les patterns
        // Pour une suppression complète par tenant, il faut utiliser Redis avec tags
        // ou maintenir une liste des clés de cache par tenant
        
        // Pour l'instant, on retourne 0 car on ne peut pas supprimer par pattern
        // sans Redis tags ou sans maintenir une liste des clés
        // En production avec Redis, utiliser:
        // Cache::tags(['tenant:' . $organizationId])->flush();
        
        Log::info("Cache clear requested for tenant {$organizationId}. Use Redis with tags for pattern deletion.");
        return 0;
    }

    /**
     * Clé de cache pour la configuration d'une activité
     * 
     * @param int $activityId ID de l'activité
     * @return string
     */
    public static function activityConfigKey(int $activityId): string
    {
        return self::ACTIVITY_CONFIG_PREFIX . ':' . $activityId;
    }

    /**
     * Clé de cache pour la configuration d'un module
     * 
     * @param string $activityType Type d'activité
     * @return string
     */
    public static function moduleConfigKey(string $activityType): string
    {
        return self::MODULE_CONFIG_PREFIX . ':' . $activityType;
    }

    /**
     * Clé de cache pour la liste des activités d'une organisation
     * 
     * @param int $organizationId ID de l'organisation
     * @param array $filters Filtres optionnels
     * @return string
     */
    public static function activitiesListKey(int $organizationId, array $filters = []): string
    {
        $filterHash = md5(json_encode($filters));
        return self::ACTIVITIES_LIST_PREFIX . ':org:' . $organizationId . ':filters:' . $filterHash;
    }

    /**
     * Clé de cache pour la liste des instructeurs d'une organisation
     * 
     * @param int $organizationId ID de l'organisation
     * @param array $filters Filtres optionnels
     * @return string
     */
    public static function instructorsListKey(int $organizationId, array $filters = []): string
    {
        $filterHash = md5(json_encode($filters));
        return self::INSTRUCTORS_LIST_PREFIX . ':org:' . $organizationId . ':filters:' . $filterHash;
    }

    /**
     * Clé de cache pour les statistiques d'une organisation
     * 
     * @param int $organizationId ID de l'organisation
     * @param string $type Type de statistiques
     * @param array $params Paramètres additionnels
     * @return string
     */
    public static function statsKey(int $organizationId, string $type, array $params = []): string
    {
        $paramsHash = md5(json_encode($params));
        return self::STATS_PREFIX . ':org:' . $organizationId . ':type:' . $type . ':params:' . $paramsHash;
    }

    /**
     * Clé de cache pour les sites d'une organisation
     * 
     * @param int $organizationId ID de l'organisation
     * @return string
     */
    public static function sitesKey(int $organizationId): string
    {
        return self::SITES_PREFIX . ':org:' . $organizationId;
    }

    /**
     * Invalider le cache d'une activité
     * 
     * @param int $organizationId ID de l'organisation
     * @param int $activityId ID de l'activité
     * @return void
     */
    public static function invalidateActivity(int $organizationId, int $activityId): void
    {
        // Invalider la configuration de l'activité (constraints et pricing)
        self::forget($organizationId, self::activityConfigKey($activityId) . ':constraints');
        self::forget($organizationId, self::activityConfigKey($activityId) . ':pricing');
        
        // Invalider aussi la clé de base
        self::forget($organizationId, self::activityConfigKey($activityId));
        
        // Invalider les listes d'activités
        // Note: On devrait invalider toutes les variantes avec filtres
        // Pour simplifier, on invalide juste les clés principales
        self::forget($organizationId, self::activitiesListKey($organizationId));
    }

    /**
     * Invalider le cache d'un module
     * 
     * @param string $activityType Type d'activité
     * @return void
     */
    public static function invalidateModule(string $activityType): void
    {
        // Les configurations de modules sont globales, pas par tenant
        $cacheKey = self::moduleConfigKey($activityType);
        Cache::forget($cacheKey);
    }

    /**
     * Invalider toutes les listes d'activités d'une organisation
     * 
     * @param int $organizationId ID de l'organisation
     * @return void
     */
    public static function invalidateActivitiesList(int $organizationId): void
    {
        // Note: En production avec Redis tags, on pourrait utiliser tags
        // Pour l'instant, on invalide juste les clés principales
        self::forget($organizationId, self::activitiesListKey($organizationId));
    }

    /**
     * Invalider toutes les listes d'instructeurs d'une organisation
     * 
     * @param int $organizationId ID de l'organisation
     * @return void
     */
    public static function invalidateInstructorsList(int $organizationId): void
    {
        self::forget($organizationId, self::instructorsListKey($organizationId));
    }

    /**
     * Invalider les statistiques d'une organisation
     * 
     * @param int $organizationId ID de l'organisation
     * @return void
     */
    public static function invalidateStats(int $organizationId): void
    {
        // Note: En production, on devrait invalider toutes les variantes
        // Pour simplifier, on utilise un pattern (nécessite Redis)
        self::clearTenant($organizationId);
    }
}

