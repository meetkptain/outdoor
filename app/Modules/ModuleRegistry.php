<?php

namespace App\Modules;

class ModuleRegistry
{
    protected array $modules = [];

    /**
     * Enregistrer un module
     */
    public function register(Module $module): void
    {
        $this->modules[$module->getType()] = $module;
    }

    /**
     * Récupérer un module par son type
     */
    public function get(string $type): ?Module
    {
        return $this->modules[$type] ?? null;
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

