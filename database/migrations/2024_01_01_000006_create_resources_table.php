<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['tandem_glider', 'site', 'vehicle', 'equipment'])
                  ->default('tandem_glider');
            
            // Caractéristiques
            $table->text('description')->nullable();
            $table->json('specifications')->nullable(); // Ex: {"capacity": 2, "weight_limit": 120}
            
            // Disponibilité
            $table->boolean('is_active')->default(true);
            $table->json('availability_schedule')->nullable(); // Horaires/disponibilités
            
            // Pour les sites
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('altitude')->nullable(); // mètres
            $table->enum('difficulty_level', ['beginner', 'intermediate', 'advanced'])->nullable();
            
            // Pour les biplaceurs
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->integer('max_weight')->nullable(); // kg
            
            // Maintenance
            $table->date('last_maintenance_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->text('maintenance_notes')->nullable();
            
            $table->timestamps();
            
            $table->index('type');
            $table->index('is_active');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
