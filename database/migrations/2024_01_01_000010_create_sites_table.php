<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            
            // Localisation
            $table->string('location');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->integer('altitude')->nullable(); // mètres
            $table->enum('difficulty_level', ['beginner', 'intermediate', 'advanced'])
                  ->default('beginner');
            
            // Caractéristiques
            $table->enum('orientation', ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW', 'multi'])
                  ->nullable();
            $table->text('wind_conditions')->nullable();
            $table->text('landing_zone_info')->nullable();
            
            // Disponibilité
            $table->boolean('is_active')->default(true);
            $table->json('seasonal_availability')->nullable(); // Mois de l'année
            
            $table->timestamps();
            
            $table->index('code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
