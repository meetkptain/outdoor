<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Liste des rapports (statistiques quotidiennes)
     */
    public function index(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        $reports = [];
        $currentDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);

        while ($currentDate->lte($endDate)) {
            $reports[] = $this->generateDailyReport($currentDate);
            $currentDate->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Rapport quotidien
     */
    public function daily(Request $request): JsonResponse
    {
        $date = $request->get('date', now()->format('Y-m-d'));
        $dateCarbon = Carbon::parse($date);
        $yesterday = $dateCarbon->copy()->subDay();

        $report = $this->generateDailyReport($dateCarbon);
        $yesterdayReport = $this->generateDailyReport($yesterday);

        // Calculer l'évolution
        $revenueEvolution = $yesterdayReport['revenue'] > 0
            ? (($report['revenue'] - $yesterdayReport['revenue']) / $yesterdayReport['revenue']) * 100
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'stats' => $report,
                'comparison' => [
                    'yesterday' => $yesterdayReport,
                    'revenue_evolution_percent' => round($revenueEvolution, 2),
                ],
            ],
        ]);
    }

    /**
     * Rapport mensuel
     */
    public function monthly(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Statistiques du mois
        $stats = [
            'month' => $month,
            'year' => $year,
            'total_reservations' => Reservation::whereBetween('created_at', [$startDate, $endDate])->count(),
            'scheduled' => Reservation::whereBetween('scheduled_at', [$startDate, $endDate])
                ->whereIn('status', ['scheduled', 'confirmed'])->count(),
            'completed' => Reservation::whereBetween('scheduled_at', [$startDate, $endDate])
                ->where('status', 'completed')->count(),
            'cancelled' => Reservation::whereBetween('updated_at', [$startDate, $endDate])
                ->where('status', 'cancelled')->count(),
            'revenue' => Payment::where('status', 'succeeded')
                ->whereBetween('captured_at', [$startDate, $endDate])
                ->sum('amount'),
            'daily_breakdown' => [],
        ];

        // Breakdown quotidien
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dailyReport = $this->generateDailyReport($currentDate);
            $stats['daily_breakdown'][] = [
                'date' => $currentDate->format('Y-m-d'),
                'revenue' => $dailyReport['revenue'],
                'completed' => $dailyReport['completed'],
            ];
            $currentDate->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Générer un rapport pour une date donnée
     */
    protected function generateDailyReport(Carbon $date): array
    {
        return [
            'date' => $date->format('Y-m-d'),
            'reservations' => Reservation::whereDate('created_at', $date)->count(),
            'scheduled' => Reservation::whereDate('scheduled_at', $date)
                ->whereIn('status', ['scheduled', 'confirmed'])->count(),
            'completed' => Reservation::whereDate('scheduled_at', $date)
                ->where('status', 'completed')->count(),
            'cancelled' => Reservation::whereDate('updated_at', $date)
                ->where('status', 'cancelled')->count(),
            'revenue' => (float) Payment::where('status', 'succeeded')
                ->whereDate('captured_at', $date)
                ->sum('amount'),
        ];
    }
}
