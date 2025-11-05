<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Payment;
use App\Models\Biplaceur;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;

class StripeTerminalService
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
     * Générer un connection token pour Stripe Terminal
     */
    public function getConnectionToken(int $biplaceurId): string
    {
        $biplaceur = Biplaceur::findOrFail($biplaceurId);

        if (!$biplaceur->can_tap_to_pay) {
            throw new \Exception('Ce biplaceur n\'a pas accès à Stripe Terminal');
        }

        try {
            $connectionToken = $this->stripe->terminal->connectionTokens->create([
                'location' => $biplaceur->stripe_terminal_location_id,
            ]);

            return $connectionToken->secret;
        } catch (ApiErrorException $e) {
            Log::error('Stripe Terminal connection token failed', [
                'biplaceur_id' => $biplaceurId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Erreur lors de la génération du token: {$e->getMessage()}");
        }
    }

    /**
     * Créer un PaymentIntent pour Tap to Pay
     */
    public function createTerminalPaymentIntent(
        Reservation $reservation,
        float $amount,
        string $currency = 'eur'
    ): array {
        try {
            $intent = $this->stripe->paymentIntents->create([
                'amount' => (int) ($amount * 100),
                'currency' => $currency,
                'capture_method' => 'automatic', // Capture automatique pour terminal
                'payment_method_types' => ['card_present'],
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'reservation_uuid' => $reservation->uuid,
                    'type' => 'terminal',
                ],
            ]);

            return [
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe Terminal PaymentIntent creation failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Erreur lors de la création du paiement: {$e->getMessage()}");
        }
    }

    /**
     * Traiter un paiement terminal (après confirmation Stripe)
     */
    public function processTerminalPayment(string $paymentIntentId, array $metadata = []): Payment
    {
        try {
            $intent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            if ($intent->status !== 'succeeded') {
                throw new \Exception("Le paiement n'a pas réussi");
            }

            $reservationId = $metadata['reservation_id'] ?? $intent->metadata->reservation_id ?? null;

            if (!$reservationId) {
                throw new \Exception('Reservation ID introuvable');
            }

            $reservation = Reservation::findOrFail($reservationId);

            // Créer l'enregistrement de paiement
            $payment = Payment::create([
                'reservation_id' => $reservation->id,
                'stripe_payment_intent_id' => $intent->id,
                'type' => 'capture',
                'amount' => $intent->amount / 100,
                'currency' => strtoupper($intent->currency),
                'status' => 'succeeded',
                'payment_source' => 'terminal',
                'terminal_location_id' => $intent->metadata->terminal_location_id ?? null,
                'payment_method_type' => 'card_present',
                'stripe_data' => $intent->toArray(),
                'captured_at' => now(),
            ]);

            // Mettre à jour la réservation
            $reservation->update([
                'payment_status' => 'captured',
            ]);

            return $payment;
        } catch (ApiErrorException $e) {
            Log::error('Stripe Terminal payment processing failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Erreur lors du traitement du paiement: {$e->getMessage()}");
        }
    }

    /**
     * Créer un QR code Checkout pour paiement
     */
    public function createQrCheckout(Reservation $reservation, float $amount): array
    {
        try {
            // Créer une session Checkout Stripe
            $session = $this->stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => "Vol parapente - Réservation #{$reservation->uuid}",
                        ],
                        'unit_amount' => (int) ($amount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => config('app.url') . "/reservations/{$reservation->uuid}/success",
                'cancel_url' => config('app.url') . "/reservations/{$reservation->uuid}",
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'reservation_uuid' => $reservation->uuid,
                ],
            ]);

            // Créer un enregistrement de paiement en attente
            $payment = Payment::create([
                'reservation_id' => $reservation->id,
                'stripe_payment_intent_id' => $session->payment_intent ?? null,
                'type' => 'capture',
                'amount' => $amount,
                'currency' => 'EUR',
                'status' => 'pending',
                'payment_source' => 'qr_code',
                'qr_code_id' => $session->id,
                'metadata' => [
                    'checkout_session_id' => $session->id,
                ],
            ]);

            return [
                'qr_code_id' => $session->id,
                'url' => $session->url,
                'payment_id' => $payment->id,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe QR Checkout creation failed', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Erreur lors de la création du QR code: {$e->getMessage()}");
        }
    }
}

