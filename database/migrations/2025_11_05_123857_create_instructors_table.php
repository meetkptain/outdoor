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
        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('activity_types')->default('[]'); // [paragliding, surfing]
            $table->string('license_number')->nullable();
            $table->json('certifications')->nullable();
            $table->integer('experience_years')->nullable();
            $table->json('availability')->nullable(); // Jours, heures, exceptions
            $table->integer('max_sessions_per_day')->nullable();
            $table->boolean('can_accept_instant_bookings')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Données spécifiques à l'activité
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('organization_id');
            $table->index('user_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instructors');
    }
};
