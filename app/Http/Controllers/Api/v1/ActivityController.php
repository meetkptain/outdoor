<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Organization;
use App\Helpers\CacheHelper;
use App\Traits\PaginatesApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Activities")
 */
class ActivityController extends Controller
{
    use PaginatesApiResponse;
    /**
     * @OA\Get(
     *     path="/api/v1/activities",
     *     summary="Liste des activités",
     *     description="Retourne la liste des activités disponibles (paragliding, surfing, etc.)",
     *     operationId="listActivities",
     *     tags={"Activities"},
     *     security={{"organization": {}}},
     *     @OA\Parameter(
     *         name="activity_type",
     *         in="query",
     *         description="Filtrer par type d'activité",
     *         required=false,
     *         @OA\Schema(type="string", example="paragliding")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des activités",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Activity"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Organisation non trouvée"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $activityType = $request->query('activity_type');
        
        // Récupérer l'organisation depuis le middleware SetTenantContext
        $organization = config('app.current_organization');
        
        if (!$organization) {
            // Fallback : depuis l'utilisateur authentifié
            $user = $request->user();
            $organization = $user ? $user->getCurrentOrganization() : null;
        }
        
        if (!$organization) {
            // Fallback : depuis la session
            if (session()->has('organization_id')) {
                $organization = Organization::find(session('organization_id'));
            }
        }
        
        if (!$organization) {
            // Fallback : depuis le header
            if ($request->hasHeader('X-Organization-ID')) {
                $organization = Organization::find($request->header('X-Organization-ID'));
            }
        }
        
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
            ], 404);
        }

        // Préparer les filtres pour la clé de cache
        $filters = [];
        if ($activityType) {
            $filters['activity_type'] = $activityType;
        }

        // Récupérer avec cache (TTL 5 minutes)
        $cacheKey = CacheHelper::activitiesListKey($organization->id, $filters);
        $activities = CacheHelper::remember(
            $organization->id,
            $cacheKey,
            300, // 5 minutes
            function () use ($organization, $activityType) {
                $query = Activity::withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true);

                if ($activityType) {
                    $query->ofType($activityType);
                }

                return $query->get();
            }
        );

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Détails d'une activité
     */
    public function show(int $id): JsonResponse
    {
        $activity = Activity::with(['sessions', 'reservations'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $activity,
        ]);
    }

    /**
     * Activités par type
     */
    public function byType(string $type): JsonResponse
    {
        $activities = Activity::ofType($type)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Créer une activité (admin)
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
            'activity_type' => 'required|string',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:1',
            'max_participants' => 'required|integer|min:1',
            'min_participants' => 'required|integer|min:1',
            'pricing_config' => 'nullable|array',
            'constraints_config' => 'nullable|array',
            'metadata' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $activity = Activity::create([
            'organization_id' => $organization->id,
            'activity_type' => $validated['activity_type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'max_participants' => $validated['max_participants'],
            'min_participants' => $validated['min_participants'],
            'pricing_config' => $validated['pricing_config'] ?? [],
            'constraints_config' => $validated['constraints_config'] ?? [],
            'metadata' => $validated['metadata'] ?? [],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Activité créée avec succès',
            'data' => $activity,
        ], 201);
    }

    /**
     * Modifier une activité (admin)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'sometimes|integer|min:1',
            'max_participants' => 'sometimes|integer|min:1',
            'min_participants' => 'sometimes|integer|min:1',
            'pricing_config' => 'nullable|array',
            'constraints_config' => 'nullable|array',
            'metadata' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $activity->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Activité mise à jour avec succès',
            'data' => $activity->fresh(),
        ]);
    }

    /**
     * Supprimer une activité (admin)
     */
    public function destroy(int $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);
        $activity->delete(); // Soft delete

        return response()->json([
            'success' => true,
            'message' => 'Activité supprimée avec succès',
        ]);
    }

    /**
     * Sessions d'une activité
     */
    public function sessions(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $activity = Activity::findOrFail($id);
        
        $query = $activity->sessions();

        if (isset($validated['start_date'])) {
            $query->where('scheduled_at', '>=', $validated['start_date']);
        }

        if (isset($validated['end_date'])) {
            $query->where('scheduled_at', '<=', $validated['end_date']);
        }

        $sessions = $query->with(['instructor', 'reservation', 'site'])
            ->orderBy('scheduled_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * Disponibilités d'une activité
     */
    public function availability(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $activity = Activity::findOrFail($id);
        
        // TODO: Implémenter la logique de disponibilité avec AvailabilitySlot
        // Pour l'instant, retourner les sessions du jour
        $sessions = $activity->sessions()
            ->whereDate('scheduled_at', $validated['date'])
            ->where('status', 'scheduled')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $validated['date'],
                'scheduled_sessions' => $sessions->count(),
                'sessions' => $sessions,
            ],
        ]);
    }
}
