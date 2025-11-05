<?php

namespace App\Services;

use App\Models\Biplaceur;
use App\Models\Reservation;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class BiplaceurService
{
    /**
     * Récupérer les vols du jour pour un biplaceur
     */
    public function getFlightsToday(int $biplaceurId): Collection
    {
        return Reservation::where('biplaceur_id', $biplaceurId)
            ->whereDate('scheduled_at', today())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->with(['client', 'options', 'payments'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Récupérer le calendrier d'un biplaceur
     */
    public function getCalendar(int $biplaceurId, string $startDate, string $endDate): Collection
    {
        return Reservation::where('biplaceur_id', $biplaceurId)
            ->whereBetween('scheduled_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->whereIn('status', ['scheduled', 'confirmed', 'completed'])
            ->with(['client', 'site', 'options'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Mettre à jour les disponibilités d'un biplaceur
     */
    public function updateAvailability(int $biplaceurId, array $availability): bool
    {
        $biplaceur = Biplaceur::findOrFail($biplaceurId);
        
        return $biplaceur->update([
            'availability' => $availability,
        ]);
    }

    /**
     * Marquer un vol comme fait
     */
    public function markFlightDone(int $reservationId, int $biplaceurId): Reservation
    {
        $reservation = Reservation::where('id', $reservationId)
            ->where('biplaceur_id', $biplaceurId)
            ->firstOrFail();

        $reservation->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Incrémenter le compteur de vols du biplaceur
        $biplaceur = $reservation->biplaceur;
        if ($biplaceur) {
            $biplaceur->incrementFlights();
        }

        return $reservation->fresh(['client', 'options', 'payments']);
    }

    /**
     * Reporter un vol (biplaceur)
     */
    public function rescheduleFlight(int $reservationId, int $biplaceurId, string $reason): Reservation
    {
        $reservation = Reservation::where('id', $reservationId)
            ->where('biplaceur_id', $biplaceurId)
            ->firstOrFail();

        $reservation->update([
            'status' => 'rescheduled',
        ]);

        // Créer un report
        $reservation->reports()->create([
            'reported_by' => auth()->id(),
            'reason' => 'client_request',
            'reason_details' => $reason,
            'original_date' => $reservation->scheduled_at,
            'is_resolved' => false,
        ]);

        return $reservation->fresh(['client', 'reports']);
    }

    /**
     * Vérifier la disponibilité d'un biplaceur pour une date/heure
     */
    public function isAvailable(int $biplaceurId, string $date, string $time = null): bool
    {
        $biplaceur = Biplaceur::findOrFail($biplaceurId);
        
        // Vérifier les disponibilités configurées
        if (!$biplaceur->isAvailableOn($date, $time)) {
            return false;
        }

        // Vérifier s'il n'y a pas déjà un vol à cette heure
        $existingReservation = Reservation::where('biplaceur_id', $biplaceurId)
            ->whereDate('scheduled_at', $date)
            ->whereTime('scheduled_at', $time ?: '00:00:00')
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->exists();

        return !$existingReservation;
    }
}

