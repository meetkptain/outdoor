<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete(); // Admin ou biplaceur
            $table->enum('reason', ['weather', 'client_request', 'equipment', 'other'])->default('weather');
            $table->text('reason_details')->nullable();
            $table->dateTime('original_date'); // Date originale reportée
            $table->dateTime('new_date')->nullable(); // Nouvelle date si assignée
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();
            
            // Index
            $table->index('reservation_id');
            $table->index('reported_by');
            $table->index('is_resolved');
            $table->index('original_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};

