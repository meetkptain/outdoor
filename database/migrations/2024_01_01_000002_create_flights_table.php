<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            
            // Participant
            $table->string('participant_first_name');
            $table->string('participant_last_name');
            $table->date('participant_birth_date')->nullable();
            $table->integer('participant_weight')->nullable();
            
            // Détails du vol
            $table->dateTime('flight_date')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->decimal('max_altitude', 8, 2)->nullable(); // mètres
            $table->text('flight_notes')->nullable();
            
            // Statut
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            
            // Options spécifiques au vol
            $table->boolean('photo_included')->default(false);
            $table->boolean('video_included')->default(false);
            $table->string('photo_url')->nullable();
            $table->string('video_url')->nullable();
            
            $table->timestamps();
            
            $table->index('reservation_id');
            $table->index('flight_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
