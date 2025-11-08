<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ReservationService;
use App\Services\PaymentService;
use App\Models\Reservation;
use App\Traits\PaginatesApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Option;

/**
 * @OA\Tag(name="Reservations")
 */
class ReservationController extends Controller
{
    use PaginatesApiResponse;

    public function __construct(
        protected ReservationService $reservationService,
        protected PaymentService $paymentService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/reservations",
     *     summary="Créer une nouvelle réservation",
     *     description="Crée une réservation pour une activité (paragliding, surfing, etc.)",
     *     operationId="createReservation",
     *     tags={"Reservations"},
     *     security={{"organization": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_email", "customer_first_name", "customer_last_name", "activity_id", "participants_count", "payment_method_id"},
     *             @OA\Property(property="customer_email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="customer_first_name", type="string", example="John"),
     *             @OA\Property(property="customer_last_name", type="string", example="Doe"),
     *             @OA\Property(property="customer_phone", type="string", nullable=true, example="+33612345678"),
     *             @OA\Property(property="customer_birth_date", type="string", format="date", nullable=true, example="1990-01-15"),
     *             @OA\Property(property="customer_weight", type="integer", nullable=true, example=75, minimum=30, maximum=150),
     *             @OA\Property(property="customer_height", type="integer", nullable=true, example=175, minimum=100, maximum=250),
     *             @OA\Property(property="activity_id", type="integer", example=1, description="ID de l'activité (remplace flight_type)"),
     *             @OA\Property(property="participants_count", type="integer", example=1, minimum=1, maximum=10),
     *             @OA\Property(property="options", type="array", nullable=true,
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(property="coupon_code", type="string", nullable=true, example="SUMMER2024"),
     *             @OA\Property(property="gift_card_code", type="string", nullable=true),
     *             @OA\Property(property="special_requests", type="string", nullable=true),
     *             @OA\Property(property="payment_type", type="string", enum={"deposit", "authorization", "both"}, example="deposit"),
     *             @OA\Property(property="payment_method_id", type="string", example="pm_1234567890", description="Stripe PaymentMethod ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Réservation créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Reservation")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_email' => 'required|email',
            'customer_phone' => 'nullable|string',
            'customer_first_name' => 'required|string|max:255',
            'customer_last_name' => 'required|string|max:255',
            'customer_birth_date' => 'nullable|date',
            'customer_weight' => 'nullable|integer|min:30|max:150',
            'customer_height' => 'nullable|integer|min:100|max:250',
            'activity_id' => 'required_without:flight_type|exists:activities,id',
            'flight_type' => 'required_without:activity_id|in:tandem,biplace,initiation,perfectionnement,autonome', // @deprecated - utiliser activity_id
            'participants_count' => 'required|integer|min:1|max:10',
            'participants' => 'nullable|array',
            'participants.*.first_name' => 'nullable|string',
            'participants.*.last_name' => 'nullable|string',
            'participants.*.birth_date' => 'nullable|date',
            'participants.*.weight' => 'nullable|integer',
            'options' => 'nullable|array',
            'options.*.id' => 'required|exists:options,id',
            'options.*.quantity' => 'nullable|integer|min:1',
            'coupon_code' => 'nullable|string',
            'gift_card_code' => 'nullable|string',
            'special_requests' => 'nullable|string',
            'payment_type' => 'nullable|in:deposit,authorization,both',
            'payment_method_id' => 'required|string', // Stripe PaymentMethod ID
        ]);

        // Support rétrocompatibilité : convertir flight_type en activity_id si nécessaire
        if (empty($validated['activity_id']) && !empty($validated['flight_type'])) {
            // Chercher l'activité paragliding par défaut
            $organization = $request->user()?->getCurrentOrganization() ?? \App\Models\Organization::first();
            $activity = \App\Models\Activity::where('organization_id', $organization->id ?? null)
                ->where('activity_type', 'paragliding')
                ->first();
            
            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activité paragliding non trouvée. Veuillez utiliser activity_id.',
                ], 400);
            }
            
            $validated['activity_id'] = $activity->id;
            $validated['metadata'] = [
                'original_flight_type' => $validated['flight_type'],
            ];
        }

        try {
            $reservation = $this->reservationService->createReservation($validated);

            // Créer le paiement Stripe
            $amount = $validated['payment_type'] === 'deposit' 
                ? $reservation->deposit_amount 
                : ($validated['payment_type'] === 'authorization' 
                    ? $reservation->total_amount 
                    : $reservation->total_amount);

            $payment = $this->paymentService->createPaymentIntent(
                $reservation,
                $amount,
                $validated['payment_method_id'],
                $validated['payment_type'] ?? 'deposit'
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'reservation' => $reservation->load(['options', 'activitySessions', 'activity']),
                    'payment' => [
                        'status' => $payment->status,
                        'client_secret' => $payment->stripe_data['client_secret'] ?? null,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Récupérer une réservation par UUID
     */
    public function show(string $uuid): JsonResponse
    {
        $reservation = Reservation::where('uuid', $uuid)
            ->with(['options', 'activitySessions', 'activity', 'site', 'instructor', 'payments'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $reservation,
        ]);
    }

    /**
     * Ajouter des options à une réservation
     */
    public function addOptions(Request $request, string $uuid): JsonResponse
    {
        $reservation = Reservation::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'options' => 'required|array',
            'options.*.id' => 'required|exists:options,id',
            'options.*.quantity' => 'nullable|integer|min:1',
            'payment_method_id' => 'required|string', // Si paiement nécessaire
        ]);

        try {
            $this->reservationService->addOptions(
                $reservation,
                $validated['options'],
                'before_flight'
            );

            // Créer un paiement complémentaire si nécessaire
            $additionalAmount = $reservation->fresh()->options_amount - $reservation->getOriginal('options_amount');
            
            if ($additionalAmount > 0) {
                $this->paymentService->createAdditionalPayment(
                    $reservation,
                    $additionalAmount,
                    $validated['payment_method_id']
                );
            }

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
     * Afficher une réservation publique (page web)
     */
    public function showPublic(string $uuid)
    {
        $reservation = Reservation::where('uuid', $uuid)->firstOrFail();

        // Retourner une vue Blade ou JSON selon le header Accept
        if (request()->expectsJson()) {
            return $this->show($uuid);
        }

        // Pour l'instant, retourner JSON (à remplacer par une vue Blade plus tard)
        return response()->json([
            'reservation' => $reservation->load(['options', 'site', 'instructor', 'payments']),
        ]);
    }

    /**
     * Afficher le formulaire d'ajout d'options (page web)
     */
    public function showAddOptions(string $uuid)
    {
        $reservation = Reservation::where('uuid', $uuid)->firstOrFail();
        $availableOptions = Option::active()->upsellable()->get();

        if (request()->expectsJson()) {
            return response()->json([
                'reservation' => $reservation,
                'available_options' => $availableOptions,
            ]);
        }

        // Pour l'instant, retourner JSON (à remplacer par une vue Blade plus tard)
        return response()->json([
            'reservation' => $reservation,
            'available_options' => $availableOptions,
        ]);
    }

    /**
     * Ajouter des options depuis une page web publique
     */
    public function addOptionsPublic(string $uuid, Request $request)
    {
        // Utiliser la même logique que la méthode API
        return $this->addOptions($request, $uuid);
    }

    /**
     * Mes réservations (client authentifié)
     */
    public function myReservations(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isClient()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $query = Reservation::query()->where(function ($subQuery) use ($user) {
            $subQuery->where('user_id', $user->id);

            if ($user->client) {
                $subQuery->orWhere('client_id', $user->client->id);
            }
        })->with(['instructor', 'activity', 'site', 'options', 'payments']);

        if ($request->filled('activity_type')) {
            $query->where('activity_type', $request->get('activity_type'));
        }

        $reservations = $this->paginateQuery(
            $query->orderBy('created_at', 'desc'),
            $request,
            15
        );

        return $this->paginatedResponse($reservations);
    }

    /**
     * Détails d'une de mes réservations (client authentifié)
     */
    public function myReservation(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->isClient()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $reservation = Reservation::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('client_id', $user->client?->id);
            })
            ->with(['instructor', 'activity', 'site', 'options', 'payments', 'signature'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $reservation,
        ]);
    }

    /**
     * Ajouter des options à ma réservation (client authentifié)
     */
    public function addOptionsToMyReservation(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->isClient()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $reservation = Reservation::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('client_id', $user->client?->id);
            })
            ->firstOrFail();

        return $this->addOptions($request, $reservation->uuid);
    }

    /**
     * Appliquer un coupon
     */
    public function applyCoupon(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => 'required|string|max:50',
        ]);

        $reservation = Reservation::where('uuid', $uuid)->firstOrFail();

        try {
            $coupon = \App\Models\Coupon::where('code', $validated['coupon_code'])->first();

            if (!$coupon || !$coupon->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code coupon invalide ou expiré',
                ], 400);
            }

            // Recalculer le total avec le coupon
            $discount = $coupon->calculateDiscount($reservation->total_amount, $reservation->activity_type);
            $reservation->update([
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->code,
                'discount_amount' => $discount,
                'total_amount' => max(0, $reservation->total_amount - $discount),
            ]);

            $coupon->increment('usage_count');

            return response()->json([
                'success' => true,
                'message' => 'Coupon appliqué avec succès',
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
     * Reporter un vol (client)
     */
    public function reschedule(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $reservation = Reservation::where('uuid', $uuid)->firstOrFail();

        // Vérifier que c'est le client propriétaire
        $user = $request->user();
        if ($user && $user->isClient()) {
            if ($reservation->user_id !== $user->id && $reservation->client_id !== $user->client?->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette réservation ne vous appartient pas',
                ], 403);
            }
        }

        try {
            $this->reservationService->rescheduleReservation($reservation, $validated['reason']);

            return response()->json([
                'success' => true,
                'message' => 'Vol reporté avec succès',
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
     * Annuler un vol (client)
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $reservation = Reservation::where('uuid', $uuid)->firstOrFail();

        // Vérifier que c'est le client propriétaire
        $user = $request->user();
        if ($user && $user->isClient()) {
            if ($reservation->user_id !== $user->id && $reservation->client_id !== $user->client?->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette réservation ne vous appartient pas',
                ], 403);
            }
        }

        try {
            $this->reservationService->cancelReservation($reservation, $validated['reason'] ?? 'Annulation client');

            return response()->json([
                'success' => true,
                'message' => 'Réservation annulée avec succès',
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
     * Historique d'une de mes réservations (client authentifié)
     */
    public function myReservationHistory(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->isClient()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        // Construire la condition pour vérifier l'appartenance
        $query = Reservation::where('id', $id);
        
        $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id);
            if ($user->client && $user->client->id) {
                $q->orWhere('client_id', $user->client->id);
            }
        });
        
        $reservation = $query->first();

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation non trouvée ou accès non autorisé',
            ], 404);
        }

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
        ]);
    }
}

