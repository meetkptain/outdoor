<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Dashboard principal (alias pour summary)
     */
    public function index(Request $request)
    {
        return $this->summary($request);
    }

    /**
     * Statistiques de réservations (par statut)
     */
    public function stats(Request $request)
    {
        $period = $request->get('period', 'month');

        $stats = $this->dashboardService->getReservationStats($period);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Résumé global
     */
    public function summary(Request $request)
    {
        $period = $request->get('period', 'month');

        $summary = $this->dashboardService->getSummary($period);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Revenus
     */
    public function revenue(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $revenue = $this->dashboardService->getRevenue(
            $validated['start_date'],
            $validated['end_date']
        );

        return response()->json([
            'success' => true,
            'data' => $revenue,
        ]);
    }

    /**
     * Statistiques des activités (sessions)
     */
    public function activityStats(Request $request)
    {
        $period = $request->get('period', 'month');
        $activityType = $request->get('activity_type');

        $stats = $this->dashboardService->getActivityStats($period, $activityType);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * @deprecated Utiliser activityStats() à la place
     */
    public function flightStats(Request $request)
    {
        $period = $request->get('period', 'month');

        $stats = $this->dashboardService->getFlightStats($period);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Top instructeurs
     */
    public function topInstructors(Request $request)
    {
        $limit = $request->get('limit', 10);
        $period = $request->get('period', 'month');
        $activityType = $request->get('activity_type');

        $topInstructors = $this->dashboardService->getTopInstructors($limit, $period, $activityType);

        return response()->json([
            'success' => true,
            'data' => $topInstructors,
        ]);
    }

    /**
     * @deprecated Utiliser topInstructors() à la place
     */
    public function topBiplaceurs(Request $request)
    {
        $limit = $request->get('limit', 10);
        $period = $request->get('period', 'month');

        $topBiplaceurs = $this->dashboardService->getTopBiplaceurs($limit, $period);

        return response()->json([
            'success' => true,
            'data' => $topBiplaceurs,
        ]);
    }
}

