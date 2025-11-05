<?php

namespace Tests\Feature\Webhooks;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mock le middleware de vérification Stripe pour les tests
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Désactiver la vérification de signature pour les tests
        $this->withoutMiddleware(\App\Http\Middleware\VerifyStripeWebhook::class);
    }

    /**
     * Test webhook payment_intent.succeeded
     */
    public function test_handles_payment_intent_succeeded_event(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => 'scheduled',
            'payment_status' => 'authorized',
        ]);

        $payment = Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'stripe_payment_intent_id' => 'pi_test_1234567890',
            'status' => 'requires_capture',
            'amount' => 100.00,
        ]);

        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_1234567890',
                    'status' => 'succeeded',
                    'amount' => 10000, // En centimes
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/stripe', $payload, [
            'Stripe-Signature' => 'test_signature',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test webhook payment_intent.payment_failed
     */
    public function test_handles_payment_intent_payment_failed_event(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $payment = Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'stripe_payment_intent_id' => 'pi_test_1234567890',
            'status' => 'requires_capture',
        ]);

        $payload = [
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_test_1234567890',
                    'status' => 'payment_failed',
                    'last_payment_error' => [
                        'message' => 'Card declined',
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/stripe', $payload, [
            'Stripe-Signature' => 'test_signature',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test webhook payment_intent.requires_capture
     */
    public function test_handles_payment_intent_requires_capture_event(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $payload = [
            'type' => 'payment_intent.requires_capture',
            'data' => [
                'object' => [
                    'id' => 'pi_test_1234567890',
                    'status' => 'requires_capture',
                    'amount' => 10000,
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/stripe', $payload, [
            'Stripe-Signature' => 'test_signature',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test webhook charge.refunded
     */
    public function test_handles_charge_refunded_event(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => 'completed',
            'payment_status' => 'captured',
        ]);

        $payment = Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'stripe_charge_id' => 'ch_test_1234567890',
            'stripe_payment_intent_id' => 'pi_test_1234567890',
            'status' => 'succeeded',
            'amount' => 100.00,
            'refunded_amount' => 0,
        ]);

        $payload = [
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_test_1234567890',
                    'payment_intent' => 'pi_test_1234567890',
                    'amount' => 10000,
                    'amount_refunded' => 5000, // Remboursement partiel
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/stripe', $payload, [
            'Stripe-Signature' => 'test_signature',
        ]);

        $response->assertStatus(200);

        // Vérifier que le paiement a été mis à jour
        $payment->refresh();
        $this->assertEquals(50.00, $payment->refunded_amount);
    }

    /**
     * Test webhook payment_intent.canceled
     */
    public function test_handles_payment_intent_canceled_event(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => 'pending',
            'payment_status' => 'authorized',
        ]);

        $payment = Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'stripe_payment_intent_id' => 'pi_test_1234567890',
            'status' => 'requires_capture',
            'type' => 'authorization',
            'amount' => 100.00,
        ]);

        $payload = [
            'type' => 'payment_intent.canceled',
            'data' => [
                'object' => [
                    'id' => 'pi_test_1234567890',
                    'status' => 'canceled',
                    'amount' => 10000,
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/stripe', $payload, [
            'Stripe-Signature' => 'test_signature',
        ]);

        $response->assertStatus(200);

        // Vérifier que le paiement a été mis à jour
        $payment->refresh();
        $this->assertEquals('canceled', $payment->status);

        // Vérifier que la réservation a été mise à jour
        $reservation->refresh();
        $this->assertEquals('failed', $reservation->payment_status); // 'canceled' n'existe pas dans l'enum, utiliser 'failed'
    }

    /**
     * Test webhook setup_intent.succeeded
     */
    public function test_handles_setup_intent_succeeded_event(): void
    {
        $reservation = Reservation::factory()->create([
            'uuid' => 'test-uuid-123',
            'metadata' => [],
        ]);

        $payload = [
            'type' => 'setup_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'seti_test_1234567890',
                    'payment_method' => 'pm_test_1234567890',
                    'metadata' => [
                        'reservation_id' => (string) $reservation->id,
                        'reservation_uuid' => $reservation->uuid,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/stripe', $payload, [
            'Stripe-Signature' => 'test_signature',
        ]);

        $response->assertStatus(200);

        // Vérifier que les metadata ont été sauvegardées
        $reservation->refresh();
        $metadata = $reservation->metadata;
        $this->assertEquals('seti_test_1234567890', $metadata['setup_intent_id']);
        $this->assertEquals('pm_test_1234567890', $metadata['payment_method_id']);
    }

    /**
     * Test rejet webhook avec signature invalide
     */
    public function test_rejects_webhook_with_invalid_signature(): void
    {
        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_1234567890',
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/stripe', $payload, [
            'Stripe-Signature' => 'invalid_signature',
        ]);

        // Le webhook devrait être rejeté si la signature est invalide
        // Note: Cela dépend de l'implémentation du middleware
        $response->assertStatus(400);
    }

}

