<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Activity Sessions")
 */
class ActivitySessionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/activity-sessions",
     *     summary="Liste des sessions d'activité",
     *     description="Retourne la liste des sessions d'activité avec filtres",
     *     operationId="listActivitySessions",
     *     tags={"Activity Sessions"},
     *     security={{"organization": {}}},
     *     @OA\Parameter(name="activity_id", in="query", @OA\Schema(type="integer"), description="Filtrer par activité"),
     *     @OA\Parameter(name="instructor_id", in="query", @OA\Schema(type="integer"), description="Filtrer par instructeur"),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"scheduled", "completed", "cancelled"}), description="Filtrer par statut"),
     *     @OA\Parameter(name="start_date", in="query", @OA\Schema(type="string", format="date"), description="Date de début"),
     *     @OA\Parameter(name="end_date", in="query", @OA\Schema(type="string", format="date"), description="Date de fin"),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des sessions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $activityId = $request->query('activity_id');
        $instructorId = $request->query('instructor_id');
        $status = $request->query('status');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = ActivitySession::with(['activity', 'instructor', 'reservation', 'site']);

        if ($activityId) {
            $query->where('activity_id', $activityId);
        }

        if ($instructorId) {
            $query->where('instructor_id', $instructorId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($startDate) {
            $query->where('scheduled_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('scheduled_at', '<=', $endDate);
        }

        $sessions = $query->orderBy('scheduled_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activity-sessions/by-activity/{activity_id}",
     *     summary="Sessions par activité",
     *     description="Retourne les sessions pour une activité spécifique",
     *     operationId="sessionsByActivity",
     *     tags={"Activity Sessions"},
     *     security={{"organization": {}}},
     *     @OA\Parameter(name="activity_id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="start_date", in="query", @OA\Schema(type="string", format="date"), description="Date de début"),
     *     @OA\Parameter(name="end_date", in="query", @OA\Schema(type="string", format="date"), description="Date de fin"),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des sessions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Activité non trouvée"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function byActivity(int $activityId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $query = ActivitySession::where('activity_id', $activityId)
            ->with(['instructor', 'reservation', 'site']);

        if (isset($validated['start_date'])) {
            $query->where('scheduled_at', '>=', $validated['start_date']);
        }

        if (isset($validated['end_date'])) {
            $query->where('scheduled_at', '<=', $validated['end_date']);
        }

        $sessions = $query->orderBy('scheduled_at')->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activity-sessions/{id}",
     *     summary="Détails d'une session",
     *     description="Retourne les détails complets d'une session d'activité",
     *     operationId="getActivitySession",
     *     tags={"Activity Sessions"},
     *     security={{"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de la session",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Session non trouvée"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $session = ActivitySession::with(['activity', 'instructor', 'reservation', 'site'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/activity-sessions",
     *     summary="Créer une session (Admin)",
     *     description="Crée une nouvelle session d'activité",
     *     operationId="createActivitySession",
     *     tags={"Activity Sessions"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"activity_id", "scheduled_at"},
     *             @OA\Property(property="activity_id", type="integer", example=1),
     *             @OA\Property(property="reservation_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time", example="2024-12-25T10:00:00Z"),
     *             @OA\Property(property="duration_minutes", type="integer", nullable=true, example=60, minimum=1),
     *             @OA\Property(property="instructor_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="site_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="status", type="string", enum={"scheduled", "completed", "cancelled"}, example="scheduled"),
     *             @OA\Property(property="metadata", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Session créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Session créée avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Organisation ou activité non trouvée"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->getCurrentOrganization();
        
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
            ], 404);
        }

        $validated = $request->validate([
            'activity_id' => 'required|exists:activities,id',
            'reservation_id' => 'nullable|exists:reservations,id',
            'scheduled_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:1',
            'instructor_id' => 'nullable|exists:instructors,id',
            'site_id' => 'nullable|exists:sites,id',
            'status' => 'nullable|in:scheduled,completed,cancelled',
            'metadata' => 'nullable|array',
        ]);

        $activity = Activity::findOrFail($validated['activity_id']);
        
        $session = ActivitySession::create([
            'organization_id' => $organization->id,
            'activity_id' => $validated['activity_id'],
            'reservation_id' => $validated['reservation_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'duration_minutes' => $validated['duration_minutes'] ?? $activity->duration_minutes,
            'instructor_id' => $validated['instructor_id'] ?? null,
            'site_id' => $validated['site_id'] ?? null,
            'status' => $validated['status'] ?? 'scheduled',
            'metadata' => $validated['metadata'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Session créée avec succès',
            'data' => $session->load(['activity', 'instructor', 'reservation']),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/activity-sessions/{id}",
     *     summary="Modifier une session (Admin)",
     *     description="Met à jour une session d'activité existante",
     *     operationId="updateActivitySession",
     *     tags={"Activity Sessions"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="scheduled_at", type="string", format="date-time", example="2024-12-25T10:00:00Z"),
     *             @OA\Property(property="duration_minutes", type="integer", nullable=true, example=60, minimum=1),
     *             @OA\Property(property="instructor_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="site_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="status", type="string", enum={"scheduled", "completed", "cancelled"}, example="completed"),
     *             @OA\Property(property="metadata", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Session mise à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Session mise à jour avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Session non trouvée"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $session = ActivitySession::findOrFail($id);

        $validated = $request->validate([
            'scheduled_at' => 'sometimes|date',
            'duration_minutes' => 'nullable|integer|min:1',
            'instructor_id' => 'nullable|exists:instructors,id',
            'site_id' => 'nullable|exists:sites,id',
            'status' => 'nullable|in:scheduled,completed,cancelled',
            'metadata' => 'nullable|array',
        ]);

        $session->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Session mise à jour avec succès',
            'data' => $session->fresh(['activity', 'instructor', 'reservation']),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/activity-sessions/{id}",
     *     summary="Supprimer une session (Admin)",
     *     description="Supprime une session d'activité (soft delete)",
     *     operationId="deleteActivitySession",
     *     tags={"Activity Sessions"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Session supprimée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Session supprimée avec succès")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Session non trouvée"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $session = ActivitySession::findOrFail($id);
        $session->delete(); // Soft delete

        return response()->json([
            'success' => true,
            'message' => 'Session supprimée avec succès',
        ]);
    }
}
