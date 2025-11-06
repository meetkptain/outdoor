<?php

namespace App\Modules;

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
    }

    /**
     * Récupérer un module par son type
     */
    public function get(string $type): ?ModuleInterface
    {
        return $this->modules[$type] ?? null;
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

