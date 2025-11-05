<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            
            // Type de réduction
            $table->enum('discount_type', ['percentage', 'fixed_amount'])
                  ->default('percentage');
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable(); // Pour % seulement
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            
            // Validité
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            
            // Utilisation
            $table->integer('usage_limit')->nullable(); // Limite globale
            $table->integer('usage_count')->default(0);
            $table->integer('usage_limit_per_user')->nullable()->default(1);
            
            // Restrictions
            $table->json('applicable_flight_types')->nullable(); // ['tandem', 'biplace']
            $table->json('applicable_options')->nullable(); // IDs des options applicables
            $table->boolean('is_first_time_only')->default(false);
            
            // Statut
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index('code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
