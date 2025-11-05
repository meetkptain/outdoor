<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biplaceurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('license_number')->nullable();
            $table->text('certifications')->nullable(); // JSON array
            $table->integer('experience_years')->nullable();
            $table->integer('total_flights')->default(0);
            $table->json('availability')->nullable(); // Format: {days: [1,2,3], hours: [9,10,11], exceptions: []}
            $table->boolean('is_active')->default(true);
            $table->boolean('can_tap_to_pay')->default(false); // A accès à Stripe Terminal
            $table->string('stripe_terminal_location_id')->nullable();
            $table->timestamps();
            
            // Index
            $table->index('user_id');
            $table->index('is_active');
            $table->index('license_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biplaceurs');
    }
};

