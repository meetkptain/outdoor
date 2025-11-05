<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['photo', 'video', 'souvenir', 'insurance', 'transport', 'other'])
                  ->default('other');
            
            // Prix
            $table->decimal('price', 10, 2);
            $table->decimal('price_per_participant', 10, 2)->nullable(); // Si prix par personne
            
            // Disponibilité
            $table->boolean('is_active')->default(true);
            $table->boolean('is_upsellable')->default(true); // Peut être proposé en upsell
            $table->integer('max_quantity')->nullable();
            
            // Affichage
            $table->integer('sort_order')->default(0);
            $table->string('icon')->nullable();
            $table->string('image_url')->nullable();
            
            $table->timestamps();
            
            $table->index('code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};
