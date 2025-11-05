<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique(); // Code affiché au client
            
            // Bénéficiaire
            $table->string('recipient_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->text('message')->nullable();
            
            // Montant
            $table->decimal('initial_amount', 10, 2);
            $table->decimal('remaining_amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            
            // Acheteur (si différent)
            $table->foreignId('purchaser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purchaser_email')->nullable();
            
            // Paiement Stripe du bon cadeau
            $table->string('stripe_payment_intent_id')->nullable();
            
            // Validité
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->integer('validity_days')->nullable(); // Valable X jours après achat
            
            // Statut
            $table->enum('status', ['pending', 'active', 'partially_used', 'used', 'expired'])
                  ->default('pending');
            
            // Utilisations
            $table->json('usage_history')->nullable(); // Historique des utilisations
            
            $table->timestamps();
            
            $table->index('code');
            $table->index('uuid');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
