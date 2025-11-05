<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // User client (si compte créé)
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete(); // Client profil (si existe)
            
            // Informations client (si pas de compte)
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->string('customer_first_name');
            $table->string('customer_last_name');
            $table->date('customer_birth_date')->nullable();
            $table->integer('customer_weight')->nullable(); // kg
            $table->integer('customer_height')->nullable(); // cm
            
            // Type de vol
            $table->enum('flight_type', ['tandem', 'biplace', 'initiation', 'perfectionnement', 'autonome'])
                  ->default('tandem');
            $table->integer('participants_count')->default(1);
            $table->text('special_requests')->nullable();
            
            // Statut
            $table->enum('status', [
                'pending',        // En attente d'assignation
                'authorized',     // Paiement autorisé (empreinte/acompte)
                'scheduled',      // Date assignée (ancien 'assigned')
                'confirmed',      // Confirmée par client
                'completed',     // Vol effectué
                'cancelled',      // Annulée
                'rescheduled',   // Reportée (météo ou autre)
                'refunded'       // Remboursée
            ])->default('pending');
            
            // Date assignée
            $table->dateTime('scheduled_at')->nullable();
            $table->time('scheduled_time')->nullable();
            
            // Ressources assignées
            $table->foreignId('biplaceur_id')->nullable()->constrained('biplaceurs')->nullOnDelete(); // Biplaceur assigné
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete(); // Ancien champ (rétrocompatibilité)
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('tandem_glider_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('resources')->nullOnDelete();
            
            // Coupon / Bon cadeau
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('gift_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable();
            
            // Montants
            $table->decimal('base_amount', 10, 2); // Montant base vol
            $table->decimal('options_amount', 10, 2)->default(0); // Options ajoutées
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2); // Total final
            $table->decimal('deposit_amount', 10, 2)->default(0); // Acompte payé
            $table->decimal('authorized_amount', 10, 2)->default(0); // Montant autorisé (empreinte)
            
            // Paiement
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->enum('payment_status', ['pending', 'authorized', 'partially_captured', 'captured', 'failed', 'refunded'])
                  ->default('pending');
            $table->enum('payment_type', ['deposit', 'authorization', 'both'])->default('deposit');
            
            // Métadonnées
            $table->json('metadata')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            // Rappel
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index
            $table->index('uuid');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('customer_email');
            $table->index('client_id');
            $table->index('biplaceur_id');
            $table->index('stripe_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
