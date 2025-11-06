<?php

namespace App\Modules\Surfing;

use App\Modules\BaseModule;
use App\Models\Reservation;
use App\Models\ActivitySession;

/**
 * Module Surfing
 * 
 * Gère la logique spécifique au surf
 */
class SurfingModule extends BaseModule
{
    /**
     * Avant création d'une réservation, vérifier les contraintes spécifiques
     */
    public function beforeReservationCreate(array $data): array
    {
        // Vérifier l'âge minimum
        if (isset($data['customer_birth_date'])) {
            $ageMin = $this->getConstraint('age.min', 8);
            $birthDate = new \DateTime($data['customer_birth_date']);
            $age = (new \DateTime())->diff($birthDate)->y;
            
            if ($age < $ageMin) {
                throw new \Exception("Âge minimum requis: {$ageMin} ans pour le surf");
            }
        }

        // Vérifier le niveau de natation si requis
        if ($this->getConstraint('swimming_level.required', false)) {
            // Logique de vérification du niveau de natation
            // À implémenter selon les besoins
        }

        return $data;
    }

    /**
     * Après création d'une réservation, actions spécifiques au surf
     */
    public function afterReservationCreate(Reservation $reservation): void
    {
        // Si réservation instantanée activée, planifier automatiquement
        if ($this->hasFeature('instant_booking') && $this->getFeature('auto_schedule', false)) {
            // Logique de planification automatique (à implémenter si nécessaire)
        }
    }

    /**
     * Avant planification d'une session, vérifier les conditions de marée
     */
    public function beforeSessionSchedule(array $data): array
    {
        // Si dépendant de la marée, vérifier les conditions
        if ($this->hasFeature('tide_dependent')) {
            // Logique de vérification de marée (à implémenter si nécessaire)
            // Utiliser TideService si disponible
        }

        // Si dépendant de la météo, vérifier les conditions
        if ($this->hasFeature('weather_dependent')) {
            // Logique de vérification météo
        }

        return $data;
    }

    /**
     * Après complétion d'une session, actions spécifiques
     */
    public function afterSessionComplete(ActivitySession $session): void
    {
        // Actions post-session (ex: retour d'équipement, etc.)
        // À implémenter selon les besoins
    }
}

