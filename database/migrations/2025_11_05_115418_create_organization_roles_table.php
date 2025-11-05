<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // admin, instructor, client, staff
            $table->json('permissions')->nullable(); // Permissions granulaires
            $table->timestamps();
            
            // Unique constraint: un utilisateur ne peut avoir qu'un seul rôle par organisation
            $table->unique(['user_id', 'organization_id']);
            
            // Index
            $table->index('organization_id');
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_roles');
    }
};
