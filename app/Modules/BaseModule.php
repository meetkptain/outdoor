<?php

namespace App\Modules;

use App\Models\Reservation;
use App\Models\ActivitySession;

/**
 * Classe de base pour tous les modules
 * 
 * Implémente ModuleInterface avec des méthodes par défaut.
 * Les modules peuvent étendre cette classe et surcharger les méthodes nécessaires.
 * Cette classe peut aussi être utilisée directement comme module par défaut.
 */
class BaseModule implements ModuleInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->config['name'] ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function getActivityType(): string
    {
        return $this->config['activity_type'] ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): string
    {
        return $this->config['version'] ?? '1.0.0';
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * {@inheritDoc}
     */
    public function getConstraints(): array
    {
        return $this->config['constraints'] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function getFeatures(): array
    {
        return $this->config['features'] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkflow(): array
    {
        return $this->config['workflow'] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function getModels(): array
    {
        return $this->config['models'] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->getFeatures()[$feature] ?? false);
    }

    /**
     * {@inheritDoc}
     */
    public function getConstraint(string $key, $default = null)
    {
        return $this->getConstraints()[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function getFeature(string $key, $default = null)
    {
        return $this->getFeatures()[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * {@inheritDoc}
     * 
     * Par défaut, aucun route n'est enregistrée.
     * Les modules peuvent surcharger cette méthode pour ajouter des routes spécifiques.
     */
    public function registerRoutes(): void
    {
        // Par défaut, aucun route spécifique
    }

    /**
     * {@inheritDoc}
     * 
     * Par défaut, aucun événement n'est enregistré.
     * Les modules peuvent surcharger cette méthode pour écouter des événements.
     */
    public function registerEvents(): void
    {
        // Par défaut, aucun événement spécifique
    }

    /**
     * {@inheritDoc}
     * 
     * Par défaut, retourne les données telles quelles.
     * Les modules peuvent surcharger pour modifier les données avant création.
     */
    public function beforeReservationCreate(array $data): array
    {
        return $data;
    }

    /**
     * {@inheritDoc}
     * 
     * Par défaut, aucune action après création.
     * Les modules peuvent surcharger pour effectuer des actions post-création.
     */
    public function afterReservationCreate(Reservation $reservation): void
    {
        // Par défaut, aucune action
    }

    /**
     * {@inheritDoc}
     * 
     * Par défaut, retourne les données telles quelles.
     * Les modules peuvent surcharger pour modifier les données avant planification.
     */
    public function beforeSessionSchedule(array $data): array
    {
        return $data;
    }

    /**
     * {@inheritDoc}
     * 
     * Par défaut, aucune action après complétion.
     * Les modules peuvent surcharger pour effectuer des actions post-complétion.
     */
    public function afterSessionComplete(ActivitySession $session): void
    {
        // Par défaut, aucune action
    }
}

