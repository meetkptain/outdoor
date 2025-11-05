<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Qui a fait le changement
            
            $table->string('action'); // assigned, status_changed, payment_captured, etc.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamps();
            
            $table->index('reservation_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_history');
    }
};
