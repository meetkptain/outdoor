<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biplaceurs', function (Blueprint $table) {
            $table->integer('max_flights_per_day')->default(5)->after('total_flights');
        });
    }

    public function down(): void
    {
        Schema::table('biplaceurs', function (Blueprint $table) {
            $table->dropColumn('max_flights_per_day');
        });
    }
};

