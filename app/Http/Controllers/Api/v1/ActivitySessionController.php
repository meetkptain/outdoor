<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivitySessionController extends Controller
{
    /**
     * Liste des sessions (public ou admin)
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
     * Sessions par activité
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
     * Détails d'une session
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
     * Créer une session (admin)
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
     * Modifier une session (admin)
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
     * Supprimer une session (admin)
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
