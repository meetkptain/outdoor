<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Payment;
use App\Models\Instructor;
use App\Models\ActivitySession;
use App\Helpers\CacheHelper;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Récupérer le résumé global (avec cache)
     */
    public function getSummary(string $period = 'month', ?int $organizationId = null): array
    {
        // Si pas d'organisation, on ne peut pas cacher par tenant
        if (!$organizationId) {
            return $this->getSummaryWithoutCache($period);
        }

        $cacheKey = CacheHelper::statsKey($organizationId, 'summary', ['period' => $period]);
        
        return CacheHelper::remember(
            $organizationId,
            $cacheKey,
            300, // 5 minutes
            fn() => $this->getSummaryWithoutCache($period)
        );
    }

    /**
     * Récupérer le résumé global sans cache
     */
    protected function getSummaryWithoutCache(string $period): array
    {
        $startDate = $this->getStartDate($period);
        $endDate = now();

        $reservations = Reservation::whereBetween('created_at', [$startDate, $endDate])->get();
        $payments = Payment::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'succeeded')
            ->get();

        return [
            'stats' => [
                'period' => $period,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_reservations' => $reservations->count(),
                'completed_sessions' => $reservations->where('status', 'completed')->count(),
                'pending_reservations' => $reservations->where('status', 'pending')->count(),
                'scheduled_sessions' => $reservations->where('status', 'scheduled')->count(),
                'total_revenue' => $payments->sum('amount'),
                'average_revenue_per_session' => $reservations->where('status', 'completed')->count() > 0
                    ? $payments->sum('amount') / $reservations->where('status', 'completed')->count()
                    : 0,
                'cancellation_rate' => $reservations->count() > 0
                    ? ($reservations->where('status', 'cancelled')->count() / $reservations->count()) * 100
                    : 0,
            ],
        ];
    }

    /**
     * Récupérer les statistiques de réservations par statut (avec cache)
     */
    public function getReservationStats(string $period = 'month', ?int $organizationId = null): array
    {
        if (!$organizationId) {
            return $this->getReservationStatsWithoutCache($period);
        }

        $cacheKey = CacheHelper::statsKey($organizationId, 'reservation_stats', ['period' => $period]);
        
        return CacheHelper::remember(
            $organizationId,
            $cacheKey,
            300, // 5 minutes
            fn() => $this->getReservationStatsWithoutCache($period)
        );
    }

    /**
     * Récupérer les statistiques de réservations sans cache
     */
    protected function getReservationStatsWithoutCache(string $period): array
    {
        $startDate = $this->getStartDate($period);
        $endDate = now();

        $reservations = Reservation::whereBetween('created_at', [$startDate, $endDate])->get();

        return [
            'reservations' => [
                'pending' => $reservations->where('status', 'pending')->count(),
                'authorized' => $reservations->where('status', 'authorized')->count(),
                'scheduled' => $reservations->where('status', 'scheduled')->count(),
                'confirmed' => $reservations->where('status', 'confirmed')->count(),
                'completed' => $reservations->where('status', 'completed')->count(),
                'cancelled' => $reservations->where('status', 'cancelled')->count(),
                'rescheduled' => $reservations->where('status', 'rescheduled')->count(),
            ],
        ];
    }

    /**
     * Récupérer les revenus par période
     */
    public function getRevenue(string $startDate, string $endDate): array
    {
        $payments = Payment::whereBetween('created_at', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay()
        ])
            ->where('status', 'succeeded')
            ->get();

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_revenue' => $payments->sum('amount'),
            'deposits' => $payments->where('type', 'deposit')->sum('amount'),
            'captures' => $payments->where('type', 'capture')->sum('amount'),
            'refunds' => $payments->where('type', 'refund')->sum('amount'),
            'by_source' => [
                'online' => $payments->where('payment_source', 'online')->sum('amount'),
                'terminal' => $payments->where('payment_source', 'terminal')->sum('amount'),
                'qr_code' => $payments->where('payment_source', 'qr_code')->sum('amount'),
            ],
        ];
    }

    /**
     * Récupérer les top instructeurs
     */
    public function getTopInstructors(int $limit = 10, string $period = 'month', ?string $activityType = null): Collection
    {
        $startDate = $this->getStartDate($period);

        $query = Instructor::with(['user', 'sessions' => function ($query) use ($startDate, $activityType) {
            $query->where('created_at', '>=', $startDate)
                ->where('status', 'completed');
            
            if ($activityType) {
                $query->whereHas('activity', function ($q) use ($activityType) {
                    $q->where('activity_type', $activityType);
                });
            }
        }]);

        return $query->get()
            ->map(function ($instructor) {
                return [
                    'id' => $instructor->id,
                    'name' => $instructor->user->name,
                    'total_sessions' => $instructor->sessions->count(),
                    'total_sessions_all_time' => ActivitySession::where('instructor_id', $instructor->id)
                        ->where('status', 'completed')
                        ->count(),
                ];
            })
            ->sortByDesc('total_sessions')
            ->take($limit)
            ->values();
    }

    /**
     * @deprecated Utiliser getTopInstructors() à la place
     */
    public function getTopBiplaceurs(int $limit = 10, string $period = 'month'): Collection
    {
        return $this->getTopInstructors($limit, $period, 'paragliding');
    }

    /**
     * Statistiques des activités (sessions)
     */
    public function getActivityStats(string $period = 'month', ?string $activityType = null): array
    {
        $startDate = $this->getStartDate($period);
        $endDate = now();

        $query = ActivitySession::whereBetween('created_at', [$startDate, $endDate]);
        
        if ($activityType) {
            $query->whereHas('activity', function ($q) use ($activityType) {
                $q->where('activity_type', $activityType);
            });
        }

        $sessions = $query->get();

        return [
            'period' => $period,
            'activity_type' => $activityType,
            'total_sessions' => $sessions->count(),
            'by_status' => [
                'pending' => $sessions->where('status', 'pending')->count(),
                'scheduled' => $sessions->where('status', 'scheduled')->count(),
                'completed' => $sessions->where('status', 'completed')->count(),
                'cancelled' => $sessions->where('status', 'cancelled')->count(),
            ],
            'by_activity_type' => $sessions->groupBy(function ($session) {
                return $session->activity->activity_type ?? 'unknown';
            })->map->count(),
            'completion_rate' => $sessions->count() > 0
                ? ($sessions->where('status', 'completed')->count() / $sessions->count()) * 100
                : 0,
        ];
    }

    /**
     * @deprecated Utiliser getActivityStats() à la place
     */
    public function getFlightStats(string $period = 'month'): array
    {
        return $this->getActivityStats($period, 'paragliding');
    }

    /**
     * Récupérer la date de début selon la période
     */
    private function getStartDate(string $period): Carbon
    {
        return match($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };
    }
}

