<?php

namespace App\Modules;

/**
 * Interface pour tous les modules d'activité
 * 
 * Cette interface standardise la structure des modules et facilite
 * l'extension et la maintenance du système multi-niche.
 */
interface ModuleInterface
{
    /**
     * Retourne le nom du module
     * 
     * @return string
     */
    public function getName(): string;

    /**
     * Retourne le type d'activité (identifiant unique)
     * 
     * @return string Ex: 'paragliding', 'surfing', 'diving'
     */
    public function getActivityType(): string;

    /**
     * Retourne la version du module
     * 
     * @return string Ex: '1.0.0'
     */
    public function getVersion(): string;

    /**
     * Retourne la configuration complète du module
     * 
     * @return array
     */
    public function getConfig(): array;

    /**
     * Retourne les contraintes de validation pour ce module
     * 
     * @return array Ex: ['weight' => ['min' => 40, 'max' => 120], 'height' => [...]]
     */
    public function getConstraints(): array;

    /**
     * Retourne les fonctionnalités activées pour ce module
     * 
     * @return array Ex: ['shuttles' => true, 'weather_dependent' => true]
     */
    public function getFeatures(): array;

    /**
     * Retourne le workflow de réservation pour ce module
     * 
     * @return array Ex: ['stages' => [...], 'auto_schedule' => true]
     */
    public function getWorkflow(): array;

    /**
     * Retourne les modèles spécifiques au module (optionnel)
     * 
     * @return array Ex: ['reservation' => ClassName, 'session' => ClassName]
     */
    public function getModels(): array;

    /**
     * Vérifie si une fonctionnalité est activée
     * 
     * @param string $feature Nom de la fonctionnalité
     * @return bool
     */
    public function hasFeature(string $feature): bool;

    /**
     * Retourne une contrainte spécifique
     * 
     * @param string $key Clé de la contrainte
     * @param mixed $default Valeur par défaut
     * @return mixed
     */
    public function getConstraint(string $key, $default = null);

    /**
     * Retourne une fonctionnalité spécifique
     * 
     * @param string $key Clé de la fonctionnalité
     * @param mixed $default Valeur par défaut
     * @return mixed
     */
    public function getFeature(string $key, $default = null);

    /**
     * Retourne une valeur de configuration
     * 
     * @param string $key Clé de configuration
     * @param mixed $default Valeur par défaut
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Enregistre les routes spécifiques au module (optionnel)
     * 
     * @return void
     */
    public function registerRoutes(): void;

    /**
     * Enregistre les événements/hooks spécifiques au module (optionnel)
     * 
     * @return void
     */
    public function registerEvents(): void;

    /**
     * Appelé avant la création d'une réservation
     * 
     * @param array $data Données de la réservation
     * @return array Données modifiées (optionnel)
     */
    public function beforeReservationCreate(array $data): array;

    /**
     * Appelé après la création d'une réservation
     * 
     * @param \App\Models\Reservation $reservation Réservation créée
     * @return void
     */
    public function afterReservationCreate(\App\Models\Reservation $reservation): void;

    /**
     * Appelé avant la planification d'une session
     * 
     * @param array $data Données de la session
     * @return array Données modifiées (optionnel)
     */
    public function beforeSessionSchedule(array $data): array;

    /**
     * Appelé après la complétion d'une session
     * 
     * @param \App\Models\ActivitySession $session Session complétée
     * @return void
     */
    public function afterSessionComplete(\App\Models\ActivitySession $session): void;
}

