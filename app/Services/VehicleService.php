<?php

namespace App\Services;

use App\Models\Resource;
use App\Models\Reservation;
use App\Models\Instructor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VehicleService
{
    /**
     * Capacité maximale par défaut d'une navette (9 places total: 8 passagers + 1 chauffeur)
     */
    const DEFAULT_MAX_CAPACITY = 9;
    const DEFAULT_MAX_PASSENGERS = 8;

    /**
     * Poids maximum par défaut pour une navette (en kg)
     */
    const DEFAULT_MAX_WEIGHT = 450;

    /**
     * Vérifier si une navette a de la capacité disponible à une date/heure donnée
     */
    public function checkCapacity(int $vehicleId, \DateTime $dateTime, ?int $excludeReservationId = null): bool
    {
        $vehicle = Resource::find($vehicleId);
        if (!$vehicle || $vehicle->type !== 'vehicle') {
            return false;
        }

        $maxPassengers = $this->getMaxPassengers($vehicle); // Passagers max (sans chauffeur)
        $currentOccupancy = $this->getCurrentOccupancy($vehicleId, $dateTime, $excludeReservationId);

        return $currentOccupancy < $maxPassengers;
    }

    /**
     * Obtenir le nombre de places disponibles dans une navette à une date/heure donnée
     */
    public function getAvailableSeats(int $vehicleId, \DateTime $dateTime, ?int $excludeReservationId = null): int
    {
        $vehicle = Resource::find($vehicleId);
        if (!$vehicle || $vehicle->type !== 'vehicle') {
            return 0;
        }

        $maxPassengers = $this->getMaxPassengers($vehicle); // Passagers max (sans chauffeur)
        $currentOccupancy = $this->getCurrentOccupancy($vehicleId, $dateTime, $excludeReservationId);

        return max(0, $maxPassengers - $currentOccupancy);
    }

    /**
     * Obtenir la capacité maximale d'une navette
     */
    public function getMaxCapacity(Resource $vehicle): int
    {
        if ($vehicle->specifications && isset($vehicle->specifications['max_capacity'])) {
            return (int) $vehicle->specifications['max_capacity'];
        }

        return self::DEFAULT_MAX_CAPACITY;
    }

    /**
     * Obtenir le nombre de passagers maximum (sans chauffeur)
     */
    public function getMaxPassengers(Resource $vehicle): int
    {
        return $this->getMaxCapacity($vehicle) - 1; // -1 pour le chauffeur
    }

    /**
     * Obtenir l'occupation actuelle d'une navette à une date/heure donnée
     * Compte les passagers (clients + instructeurs) mais pas le chauffeur
     */
    public function getCurrentOccupancy(int $vehicleId, \DateTime $dateTime, ?int $excludeReservationId = null): int
    {
        // Rechercher toutes les réservations assignées à cette navette à cette date/heure
        // On considère une fenêtre de temps pour la même rotation (ex: ±30 min)
        $startTime = Carbon::parse($dateTime)->subMinutes(30);
        $endTime = Carbon::parse($dateTime)->addMinutes(30);

        $query = Reservation::where('vehicle_id', $vehicleId)
            ->whereBetween('scheduled_at', [$startTime, $endTime])
            ->whereIn('status', ['scheduled', 'confirmed']);

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        $reservations = $query->get();

        // Compter les passagers : clients + instructeurs
        $totalOccupancy = 0;

        foreach ($reservations as $reservation) {
            // Ajouter le nombre de participants (clients)
            $totalOccupancy += $reservation->participants_count;

            // Ajouter 1 pour l'instructeur si assigné
            if ($reservation->instructor_id) {
                $totalOccupancy += 1;
            }
        }

        return $totalOccupancy;
    }

    /**
     * Vérifier si le poids total des passagers respecte la limite de la navette
     */
    public function checkWeightLimit(int $vehicleId, array $passengers, ?int $instructorId = null): bool
    {
        $vehicle = Resource::find($vehicleId);
        if (!$vehicle || $vehicle->type !== 'vehicle') {
            return false;
        }

        $maxWeight = $this->getMaxWeight($vehicle);

        // Calculer le poids total
        $totalWeight = 0;

        // Poids des passagers (clients)
        foreach ($passengers as $passenger) {
            $totalWeight += $passenger['weight'] ?? 0;
        }

        // Poids de l'instructeur si spécifié (depuis metadata ou valeur par défaut)
        if ($instructorId) {
            $instructor = Instructor::find($instructorId);
            if ($instructor) {
                $instructorWeight = $instructor->metadata['weight'] ?? 80;
                $totalWeight += $instructorWeight;
            } else {
                $totalWeight += 80; // Valeur par défaut
            }
        }

        // Poids du chauffeur (estimé à 80kg)
        $totalWeight += 80;

        return $totalWeight <= $maxWeight;
    }

    /**
     * Obtenir le poids maximum autorisé pour une navette
     */
    public function getMaxWeight(Resource $vehicle): int
    {
        if ($vehicle->specifications && isset($vehicle->specifications['max_weight'])) {
            return (int) $vehicle->specifications['max_weight'];
        }

        return self::DEFAULT_MAX_WEIGHT;
    }

    /**
     * Calculer le poids total d'une réservation (client + instructeur)
     */
    public function calculateReservationWeight(Reservation $reservation): float
    {
        $totalWeight = 0;

        // Poids du client principal
        if ($reservation->customer_weight) {
            $totalWeight += $reservation->customer_weight;
        }

        // Poids des participants additionnels (via activitySessions)
        foreach ($reservation->activitySessions as $session) {
            $participantWeight = $session->metadata['participant_weight'] ?? null;
            if ($participantWeight) {
                $totalWeight += $participantWeight;
            }
        }

        // Poids de l'instructeur si assigné (depuis metadata ou valeur par défaut)
        if ($reservation->instructor_id) {
            $instructor = Instructor::find($reservation->instructor_id);
            if ($instructor) {
                $instructorWeight = $instructor->metadata['weight'] ?? 80;
                $totalWeight += $instructorWeight;
            } else {
                $totalWeight += 80; // Valeur par défaut
            }
        }

        return $totalWeight;
    }

    /**
     * Vérifier si une réservation peut être assignée à une navette
     * Vérifie à la fois la capacité et le poids
     */
    public function canAssignReservationToVehicle(Reservation $reservation, int $vehicleId, \DateTime $dateTime): array
    {
        $errors = [];

        // Vérifier capacité
        if (!$this->checkCapacity($vehicleId, $dateTime, $reservation->id)) {
            $availableSeats = $this->getAvailableSeats($vehicleId, $dateTime, $reservation->id);
            $neededSeats = $reservation->participants_count + ($reservation->instructor_id ? 1 : 0);
            $errors[] = "Navette pleine. Places disponibles: {$availableSeats}, Places nécessaires: {$neededSeats}";
        }

        // Vérifier poids
        $reservationWeight = $this->calculateReservationWeight($reservation);
        $vehicle = Resource::find($vehicleId);
        if ($vehicle) {
            $maxWeight = $this->getMaxWeight($vehicle);
            
            // Calculer poids total avec autres réservations
            $otherReservations = Reservation::where('vehicle_id', $vehicleId)
                ->whereBetween('scheduled_at', [
                    Carbon::parse($dateTime)->subMinutes(30),
                    Carbon::parse($dateTime)->addMinutes(30)
                ])
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->where('id', '!=', $reservation->id)
                ->get();

            $totalWeight = $reservationWeight;
            foreach ($otherReservations as $otherReservation) {
                $totalWeight += $this->calculateReservationWeight($otherReservation);
            }

            // Ajouter poids chauffeur
            $totalWeight += 80;

            if ($totalWeight > $maxWeight) {
                $errors[] = "Poids total dépassé. Poids maximum: {$maxWeight}kg, Poids calculé: {$totalWeight}kg";
            }
        }

        return [
            'can_assign' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Compter le nombre de passagers d'une réservation
     */
    public function countPassengers(Reservation $reservation): int
    {
        $count = $reservation->participants_count;
        
        // Ajouter l'instructeur si assigné
        if ($reservation->instructor_id) {
            $count += 1;
        }
        
        return $count;
    }

    /**
     * Calculer le nombre de sièges nécessaires pour une réservation
     */
    public function calculateNeededSeats(Reservation $reservation): int
    {
        return $this->countPassengers($reservation);
    }

    /**
     * Obtenir toutes les navettes disponibles pour une date/heure donnée
     */
    public function getAvailableVehicles(\DateTime $dateTime, int $requiredSeats = 1): \Illuminate\Support\Collection
    {
        return Resource::where('type', 'vehicle')
            ->where('is_active', true)
            ->get()
            ->filter(function ($vehicle) use ($dateTime, $requiredSeats) {
                $availableSeats = $this->getAvailableSeats($vehicle->id, $dateTime);
                return $availableSeats >= $requiredSeats;
            });
    }
}

