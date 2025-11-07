<?php

namespace App\Modules;

use App\Helpers\CacheHelper;
use Illuminate\Support\Facades\Cache;

class ModuleRegistry
{
    protected array $modules = [];
    protected array $hooks = [];

    /**
     * Enregistrer un module
     */
    public function register(ModuleInterface $module): void
    {
        $this->modules[$module->getActivityType()] = $module;
        
        // Enregistrer les événements du module
        $module->registerEvents();
        
        // Invalider le cache du module (si disponible)
        try {
            CacheHelper::invalidateModule($module->getActivityType());
        } catch (\Exception $e) {
            // Ignorer les erreurs de cache si non disponible
        }
    }

    /**
     * Récupérer un module par son type (avec cache)
     */
    public function get(string $type): ?ModuleInterface
    {
        // Les modules sont chargés au démarrage, pas besoin de cache ici
        // Mais on peut mettre en cache la configuration du module
        return $this->modules[$type] ?? null;
    }

    /**
     * Récupérer la configuration d'un module avec cache
     */
    public function getCachedConfig(string $type): ?array
    {
        $cacheKey = CacheHelper::moduleConfigKey($type);
        
        return Cache::remember($cacheKey, 3600, function () use ($type) {
            $module = $this->get($type);
            return $module ? $module->getConfig() : null;
        });
    }

    /**
     * Enregistrer un hook pour un module
     */
    public function registerHook(ModuleHook $hook, ModuleInterface $module, callable $callback): void
    {
        $this->hooks[$hook->value][$module->getActivityType()][] = $callback;
    }

    /**
     * Déclencher un hook pour tous les modules concernés
     */
    public function triggerHook(ModuleHook $hook, string $activityType, ...$args): mixed
    {
        $callbacks = $this->hooks[$hook->value][$activityType] ?? [];
        
        $result = null;
        foreach ($callbacks as $callback) {
            $result = $callback(...$args);
        }
        
        return $result;
    }

    /**
     * Récupérer tous les modules
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Vérifier si un module est enregistré
     */
    public function has(string $type): bool
    {
        return isset($this->modules[$type]);
    }

    /**
     * Récupérer les types d'activités disponibles
     */
    public function getActivityTypes(): array
    {
        return array_keys($this->modules);
    }

    /**
     * Récupérer les modules activés pour une organisation
     */
    public function getModulesForOrganization($organizationId): array
    {
        // Pour l'instant, retourner tous les modules
        // Plus tard, filtrer selon les features de l'organisation
        return $this->all();
    }
}

