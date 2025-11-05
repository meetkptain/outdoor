<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('domain')->nullable()->unique();
            
            // Branding
            $table->string('logo_url')->nullable();
            $table->string('primary_color', 7)->nullable(); // Hex color
            $table->string('secondary_color', 7)->nullable(); // Hex color
            $table->text('custom_css')->nullable();
            
            // Configuration
            $table->json('settings')->default('{}');
            $table->json('features')->default('[]'); // Modules activés
            $table->string('subscription_tier')->default('free'); // free, starter, pro, enterprise
            
            // Facturation
            $table->string('stripe_account_id')->nullable();
            $table->string('stripe_account_status')->nullable(); // active, pending, restricted
            $table->boolean('stripe_onboarding_completed')->default(false);
            $table->string('stripe_customer_id')->nullable();
            $table->string('subscription_id')->nullable();
            $table->string('subscription_status')->nullable(); // active, cancelled, past_due
            $table->decimal('commission_rate', 5, 2)->nullable(); // Pour marketplace
            $table->string('billing_email')->nullable();
            
            // Métadonnées
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index
            $table->index('slug');
            $table->index('subscription_tier');
            $table->index('subscription_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
