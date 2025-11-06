<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Dashboard")
 */
class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/dashboard",
     *     summary="Dashboard principal",
     *     description="Retourne le résumé global du dashboard (alias pour /summary)",
     *     operationId="dashboardIndex",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Résumé du dashboard",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function index(Request $request)
    {
        return $this->summary($request);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/dashboard/stats",
     *     summary="Statistiques de réservations",
     *     description="Retourne les statistiques de réservations par statut pour une période donnée",
     *     operationId="dashboardStats",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Période (day, week, month, year)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"day", "week", "month", "year"}, default="month")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
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
     * @OA\Get(
     *     path="/api/v1/admin/dashboard/summary",
     *     summary="Résumé global du dashboard",
     *     description="Retourne un résumé complet avec statistiques, revenus et activités",
     *     operationId="dashboardSummary",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Période (day, week, month, year)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"day", "week", "month", "year"}, default="month")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Résumé du dashboard",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
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
     * @OA\Get(
     *     path="/api/v1/admin/dashboard/revenue",
     *     summary="Statistiques de revenus",
     *     description="Retourne les statistiques de revenus pour une période donnée",
     *     operationId="dashboardRevenue",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         required=true,
     *         description="Date de début",
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         required=true,
     *         description="Date de fin",
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques de revenus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
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

