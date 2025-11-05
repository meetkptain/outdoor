<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Payment;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentService
{
    protected ?StripeClient $stripe = null;

    public function __construct()
    {
        // Ne pas initialiser Stripe si la clé n'est pas configurée (pour éviter erreurs lors de l'autoload)
        $stripeSecret = config('services.stripe.secret');
        if ($stripeSecret) {
            $this->stripe = new StripeClient($stripeSecret);
        }
    }

    protected function getStripeClient(): StripeClient
    {
        if (!$this->stripe) {
            $stripeSecret = config('services.stripe.secret');
            if (!$stripeSecret) {
                throw new \Exception('Stripe secret key not configured. Please set STRIPE_SECRET in .env');
            }
            $this->stripe = new StripeClient($stripeSecret);
        }
        return $this->stripe;
    }

    /**
     * Créer un PaymentIntent avec capture manuelle (empreinte ou acompte)
     * Supporte Stripe Connect pour paiements multi-tenant
     */
    public function createPaymentIntent(
        Reservation $reservation,
        float $amount,
        string $paymentMethodId,
        string $type = 'deposit'
    ): Payment {
        $organization = $reservation->organization;

        // Si l'organisation a un compte Stripe Connect, utiliser Stripe Connect
        if ($organization && $organization->stripe_account_id && $organization->stripe_onboarding_completed) {
            return $this->createConnectPaymentIntent($reservation, $amount, $paymentMethodId, $type);
        }

        // Sinon, utiliser le compte principal (avec commission si nécessaire)
        return $this->createPlatformPaymentIntent($reservation, $amount, $paymentMethodId, $type);
    }

    /**
     * Créer un PaymentIntent via Stripe Connect (compte de l'organisation)
     */
    protected function createConnectPaymentIntent(
        Reservation $reservation,
        float $amount,
        string $paymentMethodId,
        string $type = 'deposit'
    ): Payment {
        $organization = $reservation->organization;
        $commissionRate = $organization->commission_rate ?? 5.0; // 5% par défaut
        $applicationFee = (int) ($amount * 100 * $commissionRate / 100);

        try {
            $intent = $this->getStripeClient()->paymentIntents->create([
                'amount' => (int) ($amount * 100),
                'currency' => 'eur',
                'payment_method' => $paymentMethodId,
                'capture_method' => 'manual',
                'confirmation_method' => 'manual',
                'confirm' => true,
                'application_fee_amount' => $applicationFee,
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'reservation_uuid' => $reservation->uuid,
                    'type' => $type,
                    'organization_id' => $organization->id,
                ],
                'description' => "Réservation #{$reservation->uuid} - {$type}",
            ], [
                'stripe_account' => $organization->stripe_account_id,
            ]);

            $payment = Payment::create([
                'reservation_id' => $reservation->id,
                'stripe_payment_intent_id' => $intent->id,
                'type' => $type,
                'amount' => $amount,
                'currency' => 'EUR',
                'status' => $this->mapStripeStatus($intent->status),
                'payment_method_type' => $intent->payment_method_types[0] ?? null,
                'payment_method_id' => $paymentMethodId,
                'stripe_data' => $intent->toArray(),
            ]);

            // Mettre à jour la réservation
            $reservation->update([
                'stripe_payment_intent_id' => $intent->id,
                'payment_status' => $this->mapStripeStatus($intent->status),
                'payment_type' => $type,
                'deposit_amount' => $type === 'deposit' ? $amount : $reservation->deposit_amount,
                'authorized_amount' => $type === 'authorization' ? $amount : $reservation->authorized_amount,
            ]);

            if ($intent->status === 'requires_capture') {
                $payment->update(['authorized_at' => Carbon::now()]);
            }

            return $payment;
        } catch (ApiErrorException $e) {
            Log::error('Stripe PaymentIntent creation failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Erreur de paiement: {$e->getMessage()}");
        }
    }

    /**
     * Créer un PaymentIntent sur le compte principal (plateforme)
     */
    protected function createPlatformPaymentIntent(
        Reservation $reservation,
        float $amount,
        string $paymentMethodId,
        string $type = 'deposit'
    ): Payment {
        try {
            $intent = $this->getStripeClient()->paymentIntents->create([
                'amount' => (int) ($amount * 100),
                'currency' => 'eur',
                'payment_method' => $paymentMethodId,
                'capture_method' => 'manual',
                'confirmation_method' => 'manual',
                'confirm' => true,
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'reservation_uuid' => $reservation->uuid,
                    'type' => $type,
                    'organization_id' => $reservation->organization_id,
                ],
                'description' => "Réservation #{$reservation->uuid} - {$type}",
            ]);

            $payment = Payment::create([
                'reservation_id' => $reservation->id,
                'stripe_payment_intent_id' => $intent->id,
                'type' => $type,
                'amount' => $amount,
                'currency' => 'EUR',
                'status' => $this->mapStripeStatus($intent->status),
                'payment_method_type' => $intent->payment_method_types[0] ?? null,
                'payment_method_id' => $paymentMethodId,
                'stripe_data' => $intent->toArray(),
            ]);

            // Mettre à jour la réservation
            $reservation->update([
                'stripe_payment_intent_id' => $intent->id,
                'payment_status' => $this->mapStripeStatus($intent->status),
                'payment_type' => $type,
                'deposit_amount' => $type === 'deposit' ? $amount : $reservation->deposit_amount,
                'authorized_amount' => $type === 'authorization' ? $amount : $reservation->authorized_amount,
            ]);

            if ($intent->status === 'requires_capture') {
                $payment->update(['authorized_at' => Carbon::now()]);
            }

            return $payment;
        } catch (ApiErrorException $e) {
            Log::error('Stripe PaymentIntent creation failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Erreur de paiement: {$e->getMessage()}");
        }
    }

    /**
     * Capturer un paiement autorisé
     */
    public function capturePayment(Payment $payment, ?float $amount = null): bool
    {
        if (!$payment->canBeCaptured()) {
            throw new \Exception("Le paiement ne peut pas être capturé");
        }

        $reservation = $payment->reservation;
        $organization = $reservation->organization;
        $stripeAccount = $organization && $organization->stripe_account_id ? $organization->stripe_account_id : null;

        try {
            $retrieveOptions = $stripeAccount ? ['stripe_account' => $stripeAccount] : [];
            $intent = $this->getStripeClient()->paymentIntents->retrieve(
                $payment->stripe_payment_intent_id,
                [],
                $retrieveOptions
            );

            if ($intent->status !== 'requires_capture') {
                throw new \Exception("Le PaymentIntent n'est pas en attente de capture");
            }

            $captureParams = [];
            if ($amount && $amount < $payment->amount) {
                // Capture partielle
                $captureParams['amount_to_capture'] = (int) ($amount * 100);
            }

            $captureOptions = $stripeAccount ? ['stripe_account' => $stripeAccount] : [];
            $intent = $this->getStripeClient()->paymentIntents->capture(
                $payment->stripe_payment_intent_id,
                $captureParams,
                $captureOptions
            );

            $capturedAmount = ($intent->amount_captured ?? $intent->amount) / 100;

            $payment->update([
                'status' => $this->mapStripeStatus($intent->status),
                'stripe_data' => $intent->toArray(),
                'captured_at' => Carbon::now(),
            ]);

            if ($intent->charges->data->count() > 0) {
                $charge = $intent->charges->data[0];
                $payment->update([
                    'stripe_charge_id' => $charge->id,
                    'last4' => $charge->payment_method_details->card->last4 ?? null,
                    'brand' => $charge->payment_method_details->card->brand ?? null,
                ]);
            }

            // Mettre à jour la réservation
            $reservation = $payment->reservation;
            $reservation->update([
                'payment_status' => $intent->amount_captured < $intent->amount 
                    ? 'partially_captured' 
                    : 'captured',
            ]);

            // Dispatch event
            \App\Events\PaymentCaptured::dispatch($payment->fresh(), $reservation->fresh());

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment capture failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            $payment->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            throw new \Exception("Erreur lors de la capture: {$e->getMessage()}");
        }
    }

    /**
     * Rembourser un paiement
     */
    public function refundPayment(Payment $payment, ?float $amount = null, string $reason = null): bool
    {
        if (!$payment->canBeRefunded()) {
            throw new \Exception("Le paiement ne peut pas être remboursé");
        }

        try {
            $refundAmount = $amount 
                ? (int) ($amount * 100) 
                : null; // Remboursement total si null

            $refund = $this->getStripeClient()->refunds->create([
                'payment_intent' => $payment->stripe_payment_intent_id,
                'amount' => $refundAmount,
                'reason' => $reason ?: 'requested_by_customer',
            ]);

            $refundedAmount = $refund->amount / 100;

            $payment->update([
                'stripe_refund_id' => $refund->id,
                'refunded_amount' => $refundedAmount,
                'refund_reason' => $reason,
                'status' => $refundAmount >= $payment->amount ? 'refunded' : 'partially_refunded',
                'refunded_at' => Carbon::now(),
            ]);

            // Mettre à jour la réservation
            $reservation = $payment->reservation;
            if ($refundAmount >= $payment->amount) {
                $reservation->update(['payment_status' => 'refunded']);
            }

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe refund failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Erreur lors du remboursement: {$e->getMessage()}");
        }
    }

    /**
     * Recréer une autorisation si elle expire (> 7 jours)
     */
    public function reauthorizeIfNeeded(Reservation $reservation): ?Payment
    {
        $payment = $reservation->payments()
            ->where('type', 'authorization')
            ->where('status', 'requires_capture')
            ->first();

        if (!$payment) {
            return null;
        }

        $createdAt = Carbon::parse($payment->created_at);
        if ($createdAt->diffInDays(Carbon::now()) < 7) {
            return null; // Pas besoin de réautoriser
        }

        // Annuler l'ancienne autorisation
        try {
            $this->getStripeClient()->paymentIntents->cancel($payment->stripe_payment_intent_id);
        } catch (ApiErrorException $e) {
            Log::warning('Could not cancel old authorization', ['error' => $e->getMessage()]);
        }

        // Créer une nouvelle autorisation nécessiterait les détails de la carte
        // À implémenter selon les besoins (utiliser SetupIntent pour sauvegarder la carte)
        
        return null;
    }

    /**
     * Créer un PaymentIntent complémentaire pour options ajoutées
     */
    public function createAdditionalPayment(
        Reservation $reservation,
        float $amount,
        string $paymentMethodId
    ): Payment {
        return $this->createPaymentIntent($reservation, $amount, $paymentMethodId, 'capture');
    }

    protected function mapStripeStatus(string $stripeStatus): string
    {
        return match($stripeStatus) {
            'requires_payment_method', 'requires_confirmation' => 'pending',
            'requires_capture' => 'requires_capture',
            'requires_action' => 'requires_action',
            'succeeded' => 'succeeded',
            'canceled' => 'canceled',
            default => 'failed',
        };
    }
}
