<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Payment;
use App\Models\Biplaceur;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Récupérer le résumé global
     */
    public function getSummary(string $period = 'month'): array
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
                'completed_flights' => $reservations->where('status', 'completed')->count(),
                'pending_reservations' => $reservations->where('status', 'pending')->count(),
                'scheduled_flights' => $reservations->where('status', 'scheduled')->count(),
                'total_revenue' => $payments->sum('amount'),
                'average_revenue_per_flight' => $reservations->where('status', 'completed')->count() > 0
                    ? $payments->sum('amount') / $reservations->where('status', 'completed')->count()
                    : 0,
                'cancellation_rate' => $reservations->count() > 0
                    ? ($reservations->where('status', 'cancelled')->count() / $reservations->count()) * 100
                    : 0,
            ],
        ];
    }

    /**
     * Récupérer les statistiques de réservations par statut
     */
    public function getReservationStats(string $period = 'month'): array
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
     * Récupérer les top biplaceurs
     */
    public function getTopBiplaceurs(int $limit = 10, string $period = 'month'): Collection
    {
        $startDate = $this->getStartDate($period);

        return Biplaceur::with(['user', 'reservations' => function ($query) use ($startDate) {
            $query->where('created_at', '>=', $startDate)
                ->where('status', 'completed');
        }])
            ->get()
            ->map(function ($biplaceur) {
                return [
                    'id' => $biplaceur->id,
                    'name' => $biplaceur->user->name,
                    'total_flights' => $biplaceur->reservations->count(),
                    'total_flights_all_time' => $biplaceur->total_flights,
                ];
            })
            ->sortByDesc('total_flights')
            ->take($limit)
            ->values();
    }

    /**
     * Statistiques des vols
     */
    public function getFlightStats(string $period = 'month'): array
    {
        $startDate = $this->getStartDate($period);
        $endDate = now();

        $reservations = Reservation::whereBetween('created_at', [$startDate, $endDate])->get();

        return [
            'period' => $period,
            'total_flights' => $reservations->count(),
            'by_status' => [
                'pending' => $reservations->where('status', 'pending')->count(),
                'authorized' => $reservations->where('status', 'authorized')->count(),
                'scheduled' => $reservations->where('status', 'scheduled')->count(),
                'completed' => $reservations->where('status', 'completed')->count(),
                'cancelled' => $reservations->where('status', 'cancelled')->count(),
                'rescheduled' => $reservations->where('status', 'rescheduled')->count(),
            ],
            'by_flight_type' => $reservations->groupBy('flight_type')->map->count(),
            'completion_rate' => $reservations->count() > 0
                ? ($reservations->where('status', 'completed')->count() / $reservations->count()) * 100
                : 0,
        ];
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

