<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReservationService;
use App\Services\PaymentService;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReservationAdminController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService,
        protected PaymentService $paymentService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Liste des réservations avec filtres
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

        if ($request->has('flight_type')) {
            $query->where('flight_type', $request->flight_type);
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
     * Détails d'une réservation
     */
    public function show(int $id): JsonResponse
    {
        $reservation = Reservation::with([
            'instructor',
            'site',
            'tandemGlider',
            'vehicle',
            'options',
            'flights',
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
     * Planifier une réservation (assigner date et biplaceur)
     */
    public function schedule(Request $request, int $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'scheduled_at' => 'required|date|after:now',
            'scheduled_time' => 'nullable|date_format:H:i',
            'biplaceur_id' => 'required|exists:biplaceurs,id',
            'site_id' => 'nullable|exists:sites,id',
            'tandem_glider_id' => 'nullable|exists:resources,id',
            'vehicle_id' => 'nullable|exists:resources,id',
        ]);

        try {
            $scheduledAt = \Carbon\Carbon::parse($validated['scheduled_at']);
            if ($validated['scheduled_time']) {
                $timeParts = explode(':', $validated['scheduled_time']);
                $scheduledAt->setTime($timeParts[0], $timeParts[1] ?? 0);
            }

            $this->reservationService->scheduleReservation($reservation, [
                'scheduled_at' => $scheduledAt,
                'biplaceur_id' => $validated['biplaceur_id'],
                'site_id' => $validated['site_id'] ?? null,
                'tandem_glider_id' => $validated['tandem_glider_id'] ?? null,
                'vehicle_id' => $validated['vehicle_id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Réservation planifiée avec succès',
                'data' => $reservation->fresh()->load(['biplaceur', 'site', 'tandemGlider', 'vehicle']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Assigner date et ressources (ancienne méthode, gardée pour compatibilité)
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'instructor_id' => 'required|exists:users,id',
            'site_id' => 'nullable|exists:sites,id',
            'tandem_glider_id' => 'nullable|exists:resources,id',
            'vehicle_id' => 'nullable|exists:resources,id',
        ]);

        try {
            $this->reservationService->assignResources(
                $reservation,
                new \DateTime($validated['scheduled_at']),
                $validated['instructor_id'],
                $validated['site_id'] ?? null,
                $validated['tandem_glider_id'] ?? null,
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
     * Ajouter des options (admin)
     */
    public function addOptions(Request $request, int $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'options' => 'required|array',
            'options.*.id' => 'required|exists:options,id',
            'options.*.quantity' => 'nullable|integer|min:1',
            'stage' => 'nullable|in:before_flight,after_flight',
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
     * Capturer un paiement
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
