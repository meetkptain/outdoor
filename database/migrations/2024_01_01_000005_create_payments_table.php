<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            
            // Stripe
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_charge_id')->nullable();
            $table->string('stripe_refund_id')->nullable();
            
            // Type de paiement
            $table->enum('type', ['deposit', 'authorization', 'capture', 'refund', 'adjustment'])
                  ->default('deposit');
            
            // Montants
            $table->decimal('amount', 10, 2);
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            
            // Statut
            $table->enum('status', [
                'pending',
                'requires_action',
                'requires_capture',
                'succeeded',
                'canceled',
                'failed',
                'partially_refunded',
                'refunded'
            ])->default('pending');
            
            // Méthode de paiement
            $table->string('payment_method_type')->nullable(); // card, sepa_debit, etc.
            $table->string('payment_method_id')->nullable();
            $table->enum('payment_source', ['online', 'terminal', 'qr_code'])->default('online'); // Source du paiement
            $table->string('terminal_location_id')->nullable(); // Pour Stripe Terminal
            $table->string('qr_code_id')->nullable(); // Pour QR code Checkout
            $table->string('last4')->nullable();
            $table->string('brand')->nullable(); // visa, mastercard, etc.
            
            // Métadonnées
            $table->json('stripe_data')->nullable(); // Données complètes Stripe
            $table->json('metadata')->nullable();
            
            // Raisons
            $table->text('failure_reason')->nullable();
            $table->text('refund_reason')->nullable();
            
            // Dates importantes
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            
            $table->timestamps();
            
            $table->index('reservation_id');
            $table->index('stripe_payment_intent_id');
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
