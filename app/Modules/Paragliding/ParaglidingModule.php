<?php

namespace App\Modules\Paragliding;

use App\Modules\BaseModule;
use App\Models\Reservation;
use App\Models\ActivitySession;

/**
 * Module Paragliding
 * 
 * Gère la logique spécifique au parapente
 */
class ParaglidingModule extends BaseModule
{
    /**
     * Avant création d'une réservation, vérifier les contraintes spécifiques
     */
    public function beforeReservationCreate(array $data): array
    {
        // Vérifier le poids et la taille
        if (isset($data['customer_weight'])) {
            $weightMin = $this->getConstraint('weight.min', 40);
            $weightMax = $this->getConstraint('weight.max', 120);
            
            if ($data['customer_weight'] < $weightMin || $data['customer_weight'] > $weightMax) {
                throw new \Exception("Poids doit être entre {$weightMin}kg et {$weightMax}kg pour le parapente");
            }
        }

        if (isset($data['customer_height'])) {
            $heightMin = $this->getConstraint('height.min', 140);
            $heightMax = $this->getConstraint('height.max', 250);
            
            if ($data['customer_height'] < $heightMin || $data['customer_height'] > $heightMax) {
                throw new \Exception("Taille doit être entre {$heightMin}cm et {$heightMax}cm pour le parapente");
            }
        }

        return $data;
    }

    /**
     * Après création d'une réservation, actions spécifiques au parapente
     */
    public function afterReservationCreate(Reservation $reservation): void
    {
        // Si le module a la fonctionnalité de navettes, préparer la logique
        if ($this->hasFeature('shuttles')) {
            // Logique spécifique aux navettes (à implémenter si nécessaire)
        }
    }

    /**
     * Avant planification d'une session, vérifier les conditions météo si nécessaire
     */
    public function beforeSessionSchedule(array $data): array
    {
        // Si dépendant de la météo, vérifier les conditions
        if ($this->hasFeature('weather_dependent')) {
            // Logique de vérification météo (à implémenter si nécessaire)
        }

        return $data;
    }

    /**
     * Après complétion d'une session, actions spécifiques
     */
    public function afterSessionComplete(ActivitySession $session): void
    {
        // Actions post-vol (ex: envoi de photos, vidéos, etc.)
        // À implémenter selon les besoins
    }
}

