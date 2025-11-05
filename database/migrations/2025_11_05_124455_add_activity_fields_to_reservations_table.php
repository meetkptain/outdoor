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
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('activity_type')->nullable()->after('organization_id');
            $table->foreignId('activity_id')->nullable()->after('activity_type')->constrained('activities')->nullOnDelete();
            $table->index('activity_type');
            $table->index('activity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('activity_type')->nullable()->after('organization_id');
            $table->foreignId('activity_id')->nullable()->after('activity_type')->constrained('activities')->nullOnDelete();
            $table->index('activity_type');
            $table->index('activity_id');
        });
    }
};
