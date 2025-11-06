<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReservationService;
use App\Services\PaymentService;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Reservations")
 */
class ReservationAdminController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService,
        protected PaymentService $paymentService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/reservations",
     *     summary="Liste des réservations (Admin)",
     *     description="Retourne la liste des réservations avec filtres et pagination",
     *     operationId="adminListReservations",
     *     tags={"Reservations"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string"), description="Filtrer par statut"),
     *     @OA\Parameter(name="activity_type", in="query", @OA\Schema(type="string"), description="Filtrer par type d'activité"),
     *     @OA\Parameter(name="instructor_id", in="query", @OA\Schema(type="integer"), description="Filtrer par instructeur"),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date"), description="Date de début"),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date"), description="Date de fin"),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="Recherche (email, nom, UUID)"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15), description="Nombre d'éléments par page"),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des réservations",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Reservation::with(['instructor', 'site', 'options', 'payments']);

        // Filtres
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Support rétrocompatibilité : flight_type ou activity_type
        if ($request->has('activity_type')) {
            $query->where('activity_type', $request->activity_type);
        } elseif ($request->has('flight_type')) {
            // @deprecated - utiliser activity_type à la place
            $query->where('activity_type', $request->flight_type);
        }

        if ($request->has('instructor_id')) {
            $query->where('instructor_id', $request->instructor_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('scheduled_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('scheduled_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_email', 'like', "%{$search}%")
                  ->orWhere('customer_first_name', 'like', "%{$search}%")
                  ->orWhere('customer_last_name', 'like', "%{$search}%")
                  ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        $reservations = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $reservations,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/reservations/{id}",
     *     summary="Détails d'une réservation (Admin)",
     *     description="Retourne les détails complets d'une réservation",
     *     operationId="adminGetReservation",
     *     tags={"Reservations"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de la réservation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Reservation")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Réservation non trouvée"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $reservation = Reservation::with([
            'instructor',
            'activity',
            'site',
            'vehicle',
            'options',
            'activitySessions',
            'payments',
            'history.user',
            'coupon',
            'giftCard',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $reservation,
        ]);
    }

    /**
     * Planifier une réservation (assigner date et instructeur)
     */
    public function schedule(Request $request, int $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
            'scheduled_time' => 'nullable|date_format:H:i',
            'instructor_id' => 'required_without:biplaceur_id|exists:instructors,id',
            'biplaceur_id' => 'required_without:instructor_id|exists:biplaceurs,id', // @deprecated
            'site_id' => 'nullable|exists:sites,id',
            'equipment_id' => 'nullable|exists:resources,id', // Remplacer tandem_glider_id
            'tandem_glider_id' => 'nullable|exists:resources,id', // @deprecated
            'vehicle_id' => 'nullable|exists:resources,id',
        ]);

        // Support rétrocompatibilité : convertir biplaceur_id en instructor_id
        $instructorId = $validated['instructor_id'] ?? null;
        if (!$instructorId && !empty($validated['biplaceur_id'])) {
            // Chercher l'instructor correspondant au biplaceur
            $biplaceur = \App\Models\Biplaceur::find($validated['biplaceur_id']);
            if ($biplaceur && $biplaceur->instructor_id) {
                $instructorId = $biplaceur->instructor_id;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Biplaceur non migré. Veuillez utiliser instructor_id.',
                ], 400);
            }
        }

        // Support rétrocompatibilité : equipment_id ou tandem_glider_id
        $equipmentId = $validated['equipment_id'] ?? $validated['tandem_glider_id'] ?? null;

        try {
            $scheduledAt = \Carbon\Carbon::parse($validated['scheduled_at']);
            if ($validated['scheduled_time']) {
                $timeParts = explode(':', $validated['scheduled_time']);
                $scheduledAt->setTime($timeParts[0], $timeParts[1] ?? 0);
            }

            $this->reservationService->scheduleReservation($reservation, [
                'scheduled_at' => $scheduledAt,
                'instructor_id' => $instructorId,
                'site_id' => $validated['site_id'] ?? null,
                'equipment_id' => $equipmentId,
                'vehicle_id' => $validated['vehicle_id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Réservation planifiée avec succès',
                'data' => $reservation->fresh()->load(['instructor', 'activity', 'site', 'vehicle']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/reservations/{id}/assign",
     *     summary="Assigner date et ressources à une réservation",
     *     description="Planifie une réservation en assignant un instructeur, un site et des équipements",
     *     operationId="assignReservation",
     *     tags={"Reservations"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"scheduled_at", "instructor_id"},
     *             @OA\Property(property="scheduled_at", type="string", format="date-time", example="2024-12-25T10:00:00Z"),
     *             @OA\Property(property="instructor_id", type="integer", example=1),
     *             @OA\Property(property="site_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="equipment_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="vehicle_id", type="integer", nullable=true, example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Réservation assignée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Reservation")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Erreur lors de l'assignation"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'instructor_id' => 'required|exists:instructors,id',
            'site_id' => 'nullable|exists:sites,id',
            'equipment_id' => 'nullable|exists:resources,id',
            'tandem_glider_id' => 'nullable|exists:resources,id', // @deprecated
            'vehicle_id' => 'nullable|exists:resources,id',
        ]);

        // Support rétrocompatibilité : equipment_id ou tandem_glider_id
        $equipmentId = $validated['equipment_id'] ?? $validated['tandem_glider_id'] ?? null;

        try {
            $this->reservationService->assignResources(
                $reservation,
                new \DateTime($validated['scheduled_at']),
                $validated['instructor_id'],
                $validated['site_id'] ?? null,
                $equipmentId,
                $validated['vehicle_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $reservation->fresh()->load(['instructor', 'site']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/reservations/{id}/add-options",
     *     summary="Ajouter des options à une réservation",
     *     description="Ajoute des options (photos, vidéos, etc.) à une réservation existante",
     *     operationId="addReservationOptions",
     *     tags={"Reservations"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"options"},
     *             @OA\Property(property="options", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", nullable=true, example=1)
     *                 )
     *             ),
     *             @OA\Property(property="stage", type="string", enum={"before_flight", "after_flight", "initial"}, nullable=true, example="before_flight")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Options ajoutées avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Reservation")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Erreur lors de l'ajout"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function addOptions(Request $request, int $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'options' => 'required|array',
            'options.*.id' => 'required|exists:options,id',
            'options.*.quantity' => 'nullable|integer|min:1',
            'stage' => 'nullable|string', // Stages dynamiques depuis workflow du module (before_flight/after_flight acceptés pour rétrocompatibilité)
        ]);

        try {
            $this->reservationService->addOptions(
                $reservation,
                $validated['options'],
                $validated['stage'] ?? 'before_flight'
            );

            return response()->json([
                'success' => true,
                'data' => $reservation->fresh()->load('options'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/reservations/{id}/capture",
     *     summary="Capturer un paiement pour une réservation",
     *     description="Capture un paiement autorisé pour une réservation spécifique",
     *     operationId="captureReservationPayment",
     *     tags={"Reservations"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="amount", type="number", format="float", nullable=true, example=120.00, description="Montant à capturer (optionnel, capture totale si omis)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paiement capturé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Payment")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Erreur lors de la capture"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function capture(Request $request, int $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $payment = $reservation->payments()
                ->where('status', 'requires_capture')
                ->firstOrFail();

            $this->paymentService->capturePayment(
                $payment,
                $validated['amount'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Paiement capturé avec succès',
                'data' => $payment->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Rembourser un paiement
     */
    public function refund(Request $request, int $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string',
        ]);

        try {
            $payment = $reservation->payments()
                ->where('status', 'succeeded')
                ->firstOrFail();

            $this->paymentService->refundPayment(
                $payment,
                $validated['amount'] ?? null,
                $validated['reason'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Remboursement effectué avec succès',
                'data' => $payment->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Marquer comme complété
     */
    public function complete(int $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        try {
            $this->reservationService->completeReservation($reservation);

            return response()->json([
                'success' => true,
                'message' => 'Réservation marquée comme complétée',
                'data' => $reservation->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Modifier le statut
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,authorized,scheduled,confirmed,completed,cancelled,rescheduled,refunded',
            'cancellation_reason' => 'nullable|string',
            'internal_notes' => 'nullable|string',
        ]);

        $oldStatus = $reservation->status;

        $reservation->update($validated);

        $reservation->addHistory('status_changed', [
            'status' => $oldStatus,
        ], [
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $reservation->fresh(),
        ]);
    }

    /**
     * Historique d'une réservation
     */
    public function history(int $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        $history = $reservation->history()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }
}
