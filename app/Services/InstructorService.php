<?php

namespace App\Services;

use App\Models\Instructor;
use App\Models\ActivitySession;
use App\Models\Reservation;
use App\Modules\ModuleRegistry;
use App\Modules\ModuleHook;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class InstructorService
{
    protected ModuleRegistry $moduleRegistry;

    public function __construct(ModuleRegistry $moduleRegistry)
    {
        $this->moduleRegistry = $moduleRegistry;
    }

    /**
     * Récupérer les sessions du jour pour un instructeur
     */
    public function getSessionsToday(int $instructorId): Collection
    {
        $instructor = Instructor::findOrFail($instructorId);
        
        return ActivitySession::where('instructor_id', $instructorId)
            ->whereDate('scheduled_at', today())
            ->whereIn('status', ['scheduled', 'completed'])
            ->with(['reservation.client', 'reservation.options', 'reservation.payments', 'activity', 'site'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Récupérer le calendrier d'un instructeur
     */
    public function getCalendar(int $instructorId, string $startDate, string $endDate): Collection
    {
        $instructor = Instructor::findOrFail($instructorId);
        
        return ActivitySession::where('instructor_id', $instructorId)
            ->whereBetween('scheduled_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->whereIn('status', ['scheduled', 'completed'])
            ->with(['reservation.client', 'reservation', 'site', 'activity'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Mettre à jour les disponibilités d'un instructeur
     */
    public function updateAvailability(int $instructorId, array $availability): bool
    {
        $instructor = Instructor::findOrFail($instructorId);
        
        return $instructor->update([
            'availability' => $availability,
        ]);
    }

    /**
     * Marquer une session comme faite
     */
    public function markSessionDone(int $sessionId, int $instructorId): ActivitySession
    {
        $session = ActivitySession::where('id', $sessionId)
            ->where('instructor_id', $instructorId)
            ->firstOrFail();

        $session->update([
            'status' => 'completed',
        ]);

        // Hook: Après complétion de session
        $activity = $session->activity;
        if ($activity) {
            $module = $this->moduleRegistry->get($activity->activity_type);
            if ($module) {
                $module->afterSessionComplete($session->fresh());
                $this->moduleRegistry->triggerHook(ModuleHook::AFTER_SESSION_COMPLETE, $activity->activity_type, $session->fresh());
            }
        }

        // Mettre à jour la réservation si toutes les sessions sont complétées
        if ($session->reservation) {
            $allSessionsCompleted = $session->reservation->activitySessions()
                ->where('status', '!=', 'completed')
                ->doesntExist();
            
            if ($allSessionsCompleted) {
                $session->reservation->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }
        }

        return $session->fresh(['reservation.client', 'reservation.options', 'reservation.payments', 'activity', 'site']);
    }

    /**
     * Reporter une session (instructeur)
     */
    public function rescheduleSession(int $sessionId, int $instructorId, string $reason): ActivitySession
    {
        $session = ActivitySession::where('id', $sessionId)
            ->where('instructor_id', $instructorId)
            ->firstOrFail();

        $oldScheduledAt = $session->scheduled_at;

        $session->update([
            'status' => 'cancelled',
        ]);

        // Mettre à jour le statut de la réservation si nécessaire
        if ($session->reservation) {
            $session->reservation->update([
                'status' => 'rescheduled',
            ]);

            // Créer un report si la relation existe
            if (method_exists($session->reservation, 'reports')) {
                $session->reservation->reports()->create([
                    'reported_by' => auth()->id(),
                    'reason' => 'client_request',
                    'reason_details' => $reason,
                    'original_date' => $oldScheduledAt,
                    'is_resolved' => false,
                ]);
            }
        }

        return $session->fresh(['reservation.client', 'reservation.reports', 'activity', 'site']);
    }

    /**
     * Vérifier la disponibilité d'un instructeur pour une date/heure
     */
    public function isAvailable(int $instructorId, string $date, string $time = null): bool
    {
        $instructor = Instructor::findOrFail($instructorId);
        
        // Vérifier les disponibilités configurées
        if (!$instructor->isAvailableOn($date, $time)) {
            return false;
        }

        // Vérifier s'il n'y a pas déjà une session à cette heure
        $query = ActivitySession::where('instructor_id', $instructorId)
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['scheduled', 'completed']);

        if ($time) {
            $query->whereTime('scheduled_at', $time);
        }

        $existingSession = $query->exists();

        if ($existingSession) {
            return false;
        }

        // Vérifier la limite de sessions par jour
        $sessionsToday = $instructor->getSessionsToday();
        if ($instructor->max_sessions_per_day && $sessionsToday->count() >= $instructor->max_sessions_per_day) {
            return false;
        }

        return true;
    }

    /**
     * Récupérer les statistiques d'un instructeur
     */
    public function getStats(int $instructorId, ?string $activityType = null): array
    {
        $instructor = Instructor::findOrFail($instructorId);
        
        $query = ActivitySession::where('instructor_id', $instructorId);

        if ($activityType) {
            $query->whereHas('activity', function ($q) use ($activityType) {
                $q->where('activity_type', $activityType);
            });
        }

        $totalSessions = $query->count();
        $completedSessions = (clone $query)->where('status', 'completed')->count();
        $scheduledSessions = (clone $query)->where('status', 'scheduled')->count();
        $cancelledSessions = (clone $query)->where('status', 'cancelled')->count();

        return [
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'scheduled_sessions' => $scheduledSessions,
            'cancelled_sessions' => $cancelledSessions,
            'completion_rate' => $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 2) : 0,
        ];
    }

    /**
     * Récupérer les sessions à venir pour un instructeur
     */
    public function getUpcomingSessions(int $instructorId, int $limit = 10): Collection
    {
        $instructor = Instructor::findOrFail($instructorId);
        
        return ActivitySession::where('instructor_id', $instructorId)
            ->where('scheduled_at', '>=', now())
            ->whereIn('status', ['scheduled'])
            ->with(['reservation.client', 'activity', 'site'])
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();
    }
}

