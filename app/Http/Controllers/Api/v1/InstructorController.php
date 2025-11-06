<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\Organization;
use App\Models\ActivitySession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Instructors")
 */
class InstructorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/instructors",
     *     summary="Liste des instructeurs",
     *     description="Retourne la liste des instructeurs actifs, optionnellement filtrés par type d'activité",
     *     operationId="listInstructors",
     *     tags={"Instructors"},
     *     security={{"organization": {}}},
     *     @OA\Parameter(
     *         name="activity_type",
     *         in="query",
     *         description="Filtrer par type d'activité (paragliding, surfing, etc.)",
     *         required=false,
     *         @OA\Schema(type="string", example="paragliding")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des instructeurs",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Instructor"))
     *         )
     *     ),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $activityType = $request->query('activity_type');
        
        $query = Instructor::with('user')
            ->where('is_active', true);

        if ($activityType) {
            $query->forActivityType($activityType);
        }

        $instructors = $query->get()->map(function ($instructor) {
            return [
                'id' => $instructor->id,
                'name' => $instructor->user->name ?? 'N/A',
                'activity_types' => $instructor->activity_types,
                'experience_years' => $instructor->experience_years,
                'license_number' => $instructor->license_number,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $instructors,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/instructors/by-activity/{activity_type}",
     *     summary="Instructeurs par type d'activité",
     *     description="Retourne la liste des instructeurs pour un type d'activité spécifique",
     *     operationId="instructorsByActivity",
     *     tags={"Instructors"},
     *     security={{"organization": {}}},
     *     @OA\Parameter(
     *         name="activity_type",
     *         in="path",
     *         required=true,
     *         description="Type d'activité",
     *         @OA\Schema(type="string", example="paragliding")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des instructeurs",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Instructor"))
     *         )
     *     ),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function byActivity(string $activityType): JsonResponse
    {
        $instructors = Instructor::with('user')
            ->forActivityType($activityType)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $instructors,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/instructors/{id}",
     *     summary="Détails d'un instructeur",
     *     description="Retourne les détails complets d'un instructeur (admin uniquement)",
     *     operationId="getInstructor",
     *     tags={"Instructors"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de l'instructeur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Instructor")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Instructeur non trouvé"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $instructor = Instructor::with('user', 'sessions')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $instructor->id,
                'user' => [
                    'id' => $instructor->user->id,
                    'name' => $instructor->user->name,
                    'email' => $instructor->user->email,
                    'phone' => $instructor->user->phone,
                ],
                'activity_types' => $instructor->activity_types,
                'license_number' => $instructor->license_number,
                'certifications' => $instructor->certifications,
                'experience_years' => $instructor->experience_years,
                'availability' => $instructor->availability,
                'max_sessions_per_day' => $instructor->max_sessions_per_day,
                'can_accept_instant_bookings' => $instructor->can_accept_instant_bookings,
                'is_active' => $instructor->is_active,
                'metadata' => $instructor->metadata,
            ],
        ]);
    }

    /**
     * Créer un instructeur (admin)
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'activity_types' => 'required|array',
            'activity_types.*' => 'string|in:paragliding,surfing,diving,climbing,etc',
            'license_number' => 'nullable|string|max:100',
            'certifications' => 'nullable|array',
            'experience_years' => 'nullable|integer|min:0',
            'availability' => 'nullable|array',
            'max_sessions_per_day' => 'nullable|integer|min:1',
            'can_accept_instant_bookings' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        // Créer l'utilisateur
        $user = \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);

        // Attacher l'utilisateur à l'organisation avec le rôle instructor
        $organization->users()->attach($user->id, [
            'role' => 'instructor',
            'permissions' => [],
        ]);

        // Créer l'instructeur (utiliser fill() pour que les casts s'appliquent)
        $instructor = new Instructor();
        $instructor->fill([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'activity_types' => $validated['activity_types'],
            'license_number' => $validated['license_number'] ?? null,
            'certifications' => $validated['certifications'] ?? [],
            'experience_years' => $validated['experience_years'] ?? null,
            'availability' => $validated['availability'] ?? null,
            'max_sessions_per_day' => $validated['max_sessions_per_day'] ?? 5,
            'can_accept_instant_bookings' => $validated['can_accept_instant_bookings'] ?? false,
            'metadata' => $validated['metadata'] ?? [],
        ]);
        $instructor->save();

        return response()->json([
            'success' => true,
            'message' => 'Instructeur créé avec succès',
            'data' => [
                'id' => $instructor->id,
                'user' => $user->only(['id', 'name', 'email']),
            ],
        ], 201);
    }

    /**
     * Modifier un instructeur (admin)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $instructor = Instructor::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $instructor->user_id,
            'phone' => 'nullable|string|max:20',
            'activity_types' => 'sometimes|array',
            'license_number' => 'nullable|string|max:100',
            'certifications' => 'nullable|array',
            'experience_years' => 'nullable|integer|min:0',
            'availability' => 'nullable|array',
            'max_sessions_per_day' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'can_accept_instant_bookings' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        // Mettre à jour l'utilisateur
        if (isset($validated['name']) || isset($validated['email']) || isset($validated['phone'])) {
            $instructor->user->update([
                'name' => $validated['name'] ?? $instructor->user->name,
                'email' => $validated['email'] ?? $instructor->user->email,
                'phone' => $validated['phone'] ?? $instructor->user->phone,
            ]);
        }

        // Mettre à jour l'instructeur
        $instructor->update([
            'activity_types' => $validated['activity_types'] ?? $instructor->activity_types,
            'license_number' => $validated['license_number'] ?? $instructor->license_number,
            'certifications' => $validated['certifications'] ?? $instructor->certifications,
            'experience_years' => $validated['experience_years'] ?? $instructor->experience_years,
            'availability' => $validated['availability'] ?? $instructor->availability,
            'max_sessions_per_day' => $validated['max_sessions_per_day'] ?? $instructor->max_sessions_per_day,
            'is_active' => $validated['is_active'] ?? $instructor->is_active,
            'can_accept_instant_bookings' => $validated['can_accept_instant_bookings'] ?? $instructor->can_accept_instant_bookings,
            'metadata' => $validated['metadata'] ?? $instructor->metadata,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Instructeur mis à jour avec succès',
            'data' => $instructor->fresh(['user']),
        ]);
    }

    /**
     * Supprimer un instructeur (admin)
     */
    public function destroy(int $id): JsonResponse
    {
        $instructor = Instructor::findOrFail($id);
        $instructor->delete(); // Soft delete

        return response()->json([
            'success' => true,
            'message' => 'Instructeur supprimé avec succès',
        ]);
    }

    /**
     * Mes sessions (instructeur authentifié)
     */
    public function mySessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $organization = $user->getCurrentOrganization();
        
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
            ], 404);
        }
        
        $instructor = Instructor::where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$instructor) {
            return response()->json([
                'success' => false,
                'message' => 'Instructeur non trouvé',
            ], 404);
        }

        $sessions = ActivitySession::with(['activity', 'reservation', 'site'])
            ->where('instructor_id', $instructor->id)
            ->orderBy('scheduled_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * Sessions du jour (instructeur)
     */
    public function sessionsToday(Request $request): JsonResponse
    {
        $user = $request->user();
        $organization = $user->getCurrentOrganization();
        
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
            ], 404);
        }
        
        $instructor = Instructor::where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$instructor) {
            return response()->json([
                'success' => false,
                'message' => 'Instructeur non trouvé',
            ], 404);
        }

        $sessions = $instructor->getSessionsToday();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * Calendrier (instructeur ou admin)
     */
    public function calendar(Request $request, ?int $id = null): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Si un ID est fourni (route admin), utiliser cet ID
        // Sinon, utiliser l'instructeur de l'utilisateur connecté
        if ($id) {
            $instructor = Instructor::findOrFail($id);
        } else {
            $user = $request->user();
            $organization = $user->getCurrentOrganization();
            
            if (!$organization) {
                return response()->json([
                    'success' => false,
                    'message' => 'Organization not found',
                ], 404);
            }
            
            $instructor = Instructor::where('user_id', $user->id)
                ->where('organization_id', $organization->id)
                ->first();

            if (!$instructor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instructeur non trouvé',
                ], 404);
            }
        }

        $sessions = $instructor->getCalendarSessions(
            $validated['start_date'],
            $validated['end_date']
        );

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * Mettre à jour disponibilités (instructeur)
     */
    public function updateAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'availability' => 'required|array',
            'availability.days' => 'required|array',
            'availability.days.*' => 'integer|between:1,7',
            'availability.hours' => 'nullable|array',
            'availability.hours.*' => 'integer|between:0,23',
            'availability.exceptions' => 'nullable|array',
            'availability.exceptions.*' => 'date',
        ]);

        $user = $request->user();
        $organization = $user->getCurrentOrganization();
        
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
            ], 404);
        }
        
        $instructor = Instructor::where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$instructor) {
            return response()->json([
                'success' => false,
                'message' => 'Instructeur non trouvé',
            ], 404);
        }

        $instructor->update(['availability' => $validated['availability']]);

        return response()->json([
            'success' => true,
            'message' => 'Disponibilités mises à jour avec succès',
            'data' => [
                'availability' => $instructor->fresh()->availability,
            ],
        ]);
    }

    /**
     * Marquer session comme terminée (instructeur)
     */
    public function markSessionDone(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $organization = $user->getCurrentOrganization();
        
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
            ], 404);
        }
        
        $instructor = Instructor::where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$instructor) {
            return response()->json([
                'success' => false,
                'message' => 'Instructeur non trouvé',
            ], 404);
        }

        $session = ActivitySession::where('id', $id)
            ->where('instructor_id', $instructor->id)
            ->firstOrFail();

        $session->update(['status' => 'completed']);

        // Mettre à jour la réservation si elle existe
        if ($session->reservation) {
            $session->reservation->update(['status' => 'completed']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Session marquée comme terminée',
            'data' => $session->fresh(),
        ]);
    }

    /**
     * Reporter une session (instructeur)
     */
    public function rescheduleSession(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'new_date' => 'required|date|after:today',
            'reason' => 'required|string|max:500',
        ]);

        $user = $request->user();
        $organization = $user->getCurrentOrganization();
        
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
            ], 404);
        }
        
        $instructor = Instructor::where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$instructor) {
            return response()->json([
                'success' => false,
                'message' => 'Instructeur non trouvé',
            ], 404);
        }

        $session = ActivitySession::where('id', $id)
            ->where('instructor_id', $instructor->id)
            ->firstOrFail();

        $session->update([
            'scheduled_at' => $validated['new_date'],
            'status' => 'rescheduled',
            'metadata' => array_merge($session->metadata ?? [], [
                'reschedule_reason' => $validated['reason'],
                'rescheduled_at' => now()->toDateTimeString(),
            ]),
        ]);

        // Mettre à jour la réservation si elle existe
        if ($session->reservation) {
            $session->reservation->update([
                'status' => 'rescheduled',
                'scheduled_at' => $validated['new_date'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Session reportée',
            'data' => $session->fresh(),
        ]);
    }

    /**
     * Infos rapides session (instructeur)
     */
    public function quickInfo(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $organization = $user->getCurrentOrganization();
        
        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found',
            ], 404);
        }
        
        $instructor = Instructor::where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$instructor) {
            return response()->json([
                'success' => false,
                'message' => 'Instructeur non trouvé',
            ], 404);
        }

        $session = ActivitySession::where('id', $id)
            ->where('instructor_id', $instructor->id)
            ->with(['reservation', 'activity'])
            ->firstOrFail();

        $reservation = $session->reservation;

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->id,
                'activity' => $session->activity->name ?? null,
                'scheduled_at' => $session->scheduled_at,
                'reservation' => $reservation ? [
                    'id' => $reservation->id,
                    'customer_name' => $reservation->customer_first_name . ' ' . $reservation->customer_last_name,
                    'customer_weight' => $reservation->customer_weight,
                    'customer_phone' => $reservation->customer_phone,
                    'special_requests' => $reservation->special_requests,
                ] : null,
            ],
        ]);
    }
}
