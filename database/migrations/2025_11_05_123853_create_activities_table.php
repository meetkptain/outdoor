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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type'); // paragliding, surfing, diving, etc.
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('max_participants')->default(1);
            $table->integer('min_participants')->default(1);
            $table->json('pricing_config')->nullable(); // Configuration tarification
            $table->json('constraints_config')->nullable(); // Poids, taille, niveau, etc.
            $table->json('metadata')->nullable(); // Données spécifiques à l'activité
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('organization_id');
            $table->index('activity_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
