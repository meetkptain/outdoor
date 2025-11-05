<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('signature_hash')->unique(); // Hash de la signature pour vérification
            $table->string('file_path')->nullable(); // Chemin vers l'image de la signature
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
            
            // Index
            $table->index('reservation_id');
            $table->index('signature_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};

