<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_id')->constrained()->cascadeOnDelete();
            
            // Quantité et prix au moment de l'ajout
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            
            // Quand a été ajouté
            $table->enum('added_at_stage', ['initial', 'before_flight', 'after_flight'])
                  ->default('initial');
            $table->timestamp('added_at')->useCurrent();
            
            // Si option utilisée/délivrée
            $table->boolean('is_delivered')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->text('delivery_notes')->nullable();
            
            $table->timestamps();
            
            $table->unique(['reservation_id', 'option_id', 'added_at_stage']);
            $table->index('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_options');
    }
};
