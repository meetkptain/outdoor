<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        // En environnement de test, créer un événement factice depuis le payload JSON
        if (app()->environment('testing') && $sigHeader === 'test_signature') {
            $payloadData = json_decode($payload, true);
            $event = (object) [
                'type' => $payloadData['type'] ?? null,
                'id' => 'evt_test_' . uniqid(),
                'data' => (object) [
                    'object' => (object) ($payloadData['data']['object'] ?? []),
                ],
            ];
        } else {
            try {
                $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            } catch (SignatureVerificationException $e) {
                Log::error('Stripe webhook signature verification failed', [
                    'error' => $e->getMessage(),
                ]);
                return response()->json(['error' => 'Invalid signature'], 400);
            }
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event->data->object);
                break;

            case 'payment_intent.requires_capture':
                $this->handlePaymentIntentRequiresCapture($event->data->object);
                break;

            case 'charge.refunded':
                $this->handleChargeRefunded($event->data->object);
                break;

            case 'payment_intent.canceled':
                $this->handlePaymentIntentCanceled($event->data->object);
                break;

            case 'setup_intent.succeeded':
                $this->handleSetupIntentSucceeded($event->data->object);
                break;

            default:
                Log::info('Unhandled Stripe webhook event', ['type' => $event->type]);
        }

        return response()->json(['received' => true]);
    }

    protected function handlePaymentIntentSucceeded($paymentIntent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$payment) {
            Log::warning('Payment not found for PaymentIntent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        $payment->update([
            'status' => 'succeeded',
            'stripe_data' => is_object($paymentIntent) && method_exists($paymentIntent, 'toArray') 
                ? $paymentIntent->toArray() 
                : (array) $paymentIntent,
            'captured_at' => now(),
        ]);

        // Gérer les charges (peut être un objet Stripe ou un stdClass)
        $charges = $paymentIntent->charges ?? null;
        if ($charges) {
            $chargesData = is_array($charges->data ?? null) ? $charges->data : (is_object($charges->data) ? [$charges->data] : []);
            if (count($chargesData) > 0) {
                $charge = $chargesData[0];
                $payment->update([
                    'stripe_charge_id' => $charge->id ?? null,
                    'last4' => $charge->payment_method_details->card->last4 ?? null,
                    'brand' => $charge->payment_method_details->card->brand ?? null,
                ]);
            }
        }

        $reservation = $payment->reservation;
        $reservation->update([
            'payment_status' => 'captured',
        ]);
    }

    protected function handlePaymentIntentFailed($paymentIntent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$payment) {
            return;
        }

        $payment->update([
            'status' => 'failed',
            'failure_reason' => $paymentIntent->last_payment_error->message ?? 'Unknown error',
            'stripe_data' => is_object($paymentIntent) && method_exists($paymentIntent, 'toArray') 
                ? $paymentIntent->toArray() 
                : (array) $paymentIntent,
        ]);

        $reservation = $payment->reservation;
        $reservation->update([
            'payment_status' => 'failed',
        ]);
    }

    protected function handlePaymentIntentRequiresCapture($paymentIntent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$payment) {
            return;
        }

        $payment->update([
            'status' => 'requires_capture',
            'authorized_at' => now(),
            'stripe_data' => is_object($paymentIntent) && method_exists($paymentIntent, 'toArray') 
                ? $paymentIntent->toArray() 
                : (array) $paymentIntent,
        ]);

        $reservation = $payment->reservation;
        $reservation->update([
            'payment_status' => 'authorized',
        ]);
    }

    protected function handleChargeRefunded($charge): void
    {
        $paymentIntentId = $charge->payment_intent;
        
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (!$payment) {
            return;
        }

        $refundedAmount = $charge->amount_refunded / 100;

        $payment->update([
            'refunded_amount' => $refundedAmount,
            'status' => $refundedAmount >= $payment->amount ? 'refunded' : 'partially_refunded',
            'refunded_at' => now(),
        ]);

        $reservation = $payment->reservation;
        if ($refundedAmount >= $payment->amount) {
            $reservation->update([
                'payment_status' => 'refunded',
            ]);
        }
    }

    /**
     * Gérer l'annulation d'un PaymentIntent
     * Se produit quand un PaymentIntent est annulé (par l'utilisateur ou automatiquement)
     */
    protected function handlePaymentIntentCanceled($paymentIntent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$payment) {
            Log::warning('Payment not found for canceled PaymentIntent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        $payment->update([
            'status' => 'canceled',
            'stripe_data' => is_object($paymentIntent) && method_exists($paymentIntent, 'toArray') 
                ? $paymentIntent->toArray() 
                : (array) $paymentIntent,
        ]);

        $reservation = $payment->reservation;
        
        // Si le paiement était une autorisation, on peut libérer la réservation
        // Si c'était un acompte, on peut annuler la réservation
        if ($payment->type === 'authorization') {
            // Autorisation annulée, on peut libérer la réservation
            $reservation->update([
                'payment_status' => 'failed', // 'canceled' n'existe pas dans l'enum, utiliser 'failed'
                'authorized_amount' => 0,
            ]);
        } elseif ($payment->type === 'deposit') {
            // Acompte annulé, la réservation peut être annulée
            // Note: On ne l'annule pas automatiquement, on laisse l'admin décider
            $reservation->update([
                'payment_status' => 'failed', // 'canceled' n'existe pas dans l'enum, utiliser 'failed'
                'deposit_amount' => 0,
            ]);
            
            Log::info('PaymentIntent canceled for reservation', [
                'reservation_id' => $reservation->id,
                'reservation_uuid' => $reservation->uuid,
                'payment_type' => $payment->type,
            ]);
        }
    }

    /**
     * Gérer la réussite d'un SetupIntent
     * Se produit quand un SetupIntent réussit (méthode de paiement sauvegardée)
     * Utile pour sauvegarder les méthodes de paiement pour paiements futurs
     */
    protected function handleSetupIntentSucceeded($setupIntent): void
    {
        // Récupérer le reservation_id depuis les metadata si présent
        $metadata = is_object($setupIntent->metadata ?? null) ? (array) $setupIntent->metadata : ($setupIntent->metadata ?? []);
        $reservationId = $metadata['reservation_id'] ?? null;
        $reservationUuid = $metadata['reservation_uuid'] ?? null;

        if (!$reservationId && !$reservationUuid) {
            Log::info('SetupIntent succeeded without reservation metadata', [
                'setup_intent_id' => $setupIntent->id,
            ]);
            return;
        }

        // Trouver la réservation
        $reservation = null;
        if ($reservationId) {
            $reservation = Reservation::find($reservationId);
        } elseif ($reservationUuid) {
            $reservation = Reservation::where('uuid', $reservationUuid)->first();
        }

        if (!$reservation) {
            Log::warning('Reservation not found for SetupIntent', [
                'setup_intent_id' => $setupIntent->id,
                'reservation_id' => $reservationId,
                'reservation_uuid' => $reservationUuid,
            ]);
            return;
        }

        // Sauvegarder la méthode de paiement pour usage futur
        // On peut stocker setup_intent_id dans les metadata de la réservation
        $metadata = $reservation->metadata ?? [];
        $metadata['setup_intent_id'] = $setupIntent->id;
        $metadata['payment_method_id'] = $setupIntent->payment_method ?? null;
        $metadata['setup_intent_succeeded_at'] = now()->toISOString();

        $reservation->update([
            'metadata' => $metadata,
        ]);

        Log::info('SetupIntent succeeded for reservation', [
            'reservation_id' => $reservation->id,
            'reservation_uuid' => $reservation->uuid,
            'setup_intent_id' => $setupIntent->id,
            'payment_method_id' => $setupIntent->payment_method,
        ]);
    }
}
