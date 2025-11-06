<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Payment;
use App\Models\Instructor;
use App\Services\PaymentService;
use App\Services\StripeTerminalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="Payments")
 */
class PaymentController extends Controller
{
    protected PaymentService $paymentService;
    protected StripeTerminalService $terminalService;

    public function __construct(
        PaymentService $paymentService,
        StripeTerminalService $terminalService
    ) {
        $this->paymentService = $paymentService;
        $this->terminalService = $terminalService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/intent",
     *     summary="Créer un PaymentIntent Stripe",
     *     description="Crée un PaymentIntent Stripe pour une réservation",
     *     operationId="createPaymentIntent",
     *     tags={"Payments"},
     *     security={{"organization": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reservation_id", "amount", "payment_method_id", "type"},
     *             @OA\Property(property="reservation_id", type="integer", example=1),
     *             @OA\Property(property="amount", type="number", format="float", example=120.00, minimum=0.01),
     *             @OA\Property(property="payment_method_id", type="string", example="pm_1234567890"),
     *             @OA\Property(property="type", type="string", enum={"deposit", "authorization", "both"}, example="deposit")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PaymentIntent créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="payment", ref="#/components/schemas/Payment"),
     *                 @OA\Property(property="client_secret", type="string", example="pi_xxx_secret_xxx")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=500, description="Erreur lors de la création"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function createIntent(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method_id' => 'required|string',
            'type' => 'required|in:deposit,authorization,both',
        ]);

        try {
            $reservation = Reservation::findOrFail($validated['reservation_id']);

            $payment = $this->paymentService->createPaymentIntent(
                $reservation,
                $validated['amount'],
                $validated['payment_method_id'],
                $validated['type']
            );

            // Mettre à jour le statut de la réservation si nécessaire
            if ($payment->status === 'requires_capture') {
                $reservation->update(['status' => 'authorized']);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'status' => $payment->status,
                        'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                    ],
                    'client_secret' => $payment->stripe_data['client_secret'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('PaymentIntent creation failed', [
                'error' => $e->getMessage(),
                'request' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du paiement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/capture",
     *     summary="Capturer un paiement autorisé",
     *     description="Capture un paiement qui a été préalablement autorisé (empreinte)",
     *     operationId="capturePayment",
     *     tags={"Payments"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_id"},
     *             @OA\Property(property="payment_id", type="integer", example=1),
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
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function capture(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        try {
            $payment = Payment::findOrFail($validated['payment_id']);

            // Vérifier que l'utilisateur a le droit de capturer
            $user = $request->user();
            $organization = $user->getCurrentOrganization();
            $instructor = $organization ? $user->getInstructorForOrganization($organization) : null;
            $isInstructor = $instructor !== null;
            
            if (!$user->isAdmin() && !$isInstructor && !$user->isBiplaceur()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé',
                ], 403);
            }

            // Si instructeur, vérifier que c'est sa réservation
            if ($isInstructor && $payment->reservation->instructor_id !== $instructor->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce paiement ne vous appartient pas',
                ], 403);
            }
            
            // Rétrocompatibilité : si biplaceur (sans instructor), vérifier biplaceur_id
            if (!$isInstructor && $user->isBiplaceur() && $user->biplaceur) {
                if ($payment->reservation->instructor_id !== $user->biplaceur->instructor_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ce paiement ne vous appartient pas',
                    ], 403);
                }
            }

            $amount = $validated['amount'] ?? null;
            $this->paymentService->capturePayment($payment, $amount);

            return response()->json([
                'success' => true,
                'message' => 'Paiement capturé avec succès',
                'data' => [
                    'payment' => [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'status' => $payment->fresh()->status,
                        'captured_at' => $payment->fresh()->captured_at,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Payment capture failed', [
                'error' => $e->getMessage(),
                'payment_id' => $validated['payment_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la capture du paiement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rembourser un paiement
     */
    public function refund(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $payment = Payment::findOrFail($validated['payment_id']);

            // Seuls les admins peuvent rembourser
            if (!$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé',
                ], 403);
            }

            $amount = $validated['amount'] ?? null;
            $reason = $validated['reason'] ?? null;

            $this->paymentService->refundPayment($payment, $amount, $reason);

            return response()->json([
                'success' => true,
                'message' => 'Remboursement effectué avec succès',
                'data' => [
                    'payment' => [
                        'id' => $payment->id,
                        'refunded_amount' => $payment->fresh()->refunded_amount,
                        'status' => $payment->fresh()->status,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Payment refund failed', [
                'error' => $e->getMessage(),
                'payment_id' => $validated['payment_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du remboursement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir un connection token Stripe Terminal
     */
    public function getTerminalConnectionToken(Request $request)
    {
        try {
            $user = $request->user();
            $organization = $user->getCurrentOrganization();
            $instructor = $organization ? $user->getInstructorForOrganization($organization) : null;

            if (!$instructor) {
                // Rétrocompatibilité : vérifier si biplaceur
                if (!$user->isBiplaceur() || !$user->biplaceur) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès non autorisé - Instructeur non trouvé',
                    ], 403);
                }
                // Si biplaceur, utiliser instructor_id du biplaceur
                $biplaceur = $user->biplaceur;
                if ($biplaceur->instructor_id) {
                    $instructor = Instructor::find($biplaceur->instructor_id);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Biplaceur non migré vers Instructor',
                    ], 400);
                }
            }

            $token = $this->terminalService->getConnectionToken($instructor->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'connection_token' => $token,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Terminal connection token failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Créer un PaymentIntent pour Terminal (Tap to Pay)
     */
    public function createTerminalPaymentIntent(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $user = $request->user();
            $organization = $user->getCurrentOrganization();
            $instructor = $organization ? $user->getInstructorForOrganization($organization) : null;

            if (!$instructor) {
                // Rétrocompatibilité : vérifier si biplaceur
                if (!$user->isBiplaceur() || !$user->biplaceur) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès non autorisé - Instructeur non trouvé',
                    ], 403);
                }
                // Si biplaceur, utiliser instructor_id du biplaceur
                $biplaceur = $user->biplaceur;
                if ($biplaceur->instructor_id) {
                    $instructor = Instructor::find($biplaceur->instructor_id);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Biplaceur non migré vers Instructor',
                    ], 400);
                }
            }

            $reservation = Reservation::findOrFail($validated['reservation_id']);

            // Vérifier que c'est l'instructeur assigné
            if ($reservation->instructor_id !== $instructor->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette réservation ne vous est pas assignée',
                ], 403);
            }

            $result = $this->terminalService->createTerminalPaymentIntent(
                $reservation,
                $validated['amount']
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Terminal PaymentIntent creation failed', [
                'error' => $e->getMessage(),
                'request' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du paiement terminal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Créer un QR code Checkout
     */
    public function createQrCheckout(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $user = $request->user();
            $organization = $user->getCurrentOrganization();
            $instructor = $organization ? $user->getInstructorForOrganization($organization) : null;

            if (!$instructor) {
                // Rétrocompatibilité : vérifier si biplaceur
                if (!$user->isBiplaceur() || !$user->biplaceur) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès non autorisé - Instructeur non trouvé',
                    ], 403);
                }
                // Si biplaceur, utiliser instructor_id du biplaceur
                $biplaceur = $user->biplaceur;
                if ($biplaceur->instructor_id) {
                    $instructor = Instructor::find($biplaceur->instructor_id);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Biplaceur non migré vers Instructor',
                    ], 400);
                }
            }

            $reservation = Reservation::findOrFail($validated['reservation_id']);

            // Vérifier que c'est l'instructeur assigné
            if ($reservation->instructor_id !== $instructor->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette réservation ne vous est pas assignée',
                ], 403);
            }

            $result = $this->terminalService->createQrCheckout(
                $reservation,
                $validated['amount']
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('QR Checkout creation failed', [
                'error' => $e->getMessage(),
                'request' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du QR code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

