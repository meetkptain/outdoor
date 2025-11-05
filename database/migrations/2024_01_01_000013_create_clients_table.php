<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone')->nullable();
            $table->integer('weight')->nullable(); // kg
            $table->integer('height')->nullable(); // cm
            $table->text('medical_notes')->nullable();
            $table->text('notes')->nullable();
            $table->integer('total_flights')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->date('last_flight_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Index
            $table->index('user_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

