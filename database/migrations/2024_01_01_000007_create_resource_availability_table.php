<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Si absence moniteur
            
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            
            $table->enum('type', ['available', 'unavailable', 'maintenance', 'reserved'])
                  ->default('available');
            $table->text('reason')->nullable();
            
            $table->timestamps();
            
            $table->index(['resource_id', 'date']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_availability');
    }
};
