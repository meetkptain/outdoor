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
        // Créer une organisation par défaut si elle n'existe pas
        $defaultOrg = \App\Models\Organization::firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default Organization',
                'subscription_tier' => 'free',
                'subscription_status' => 'active',
                'settings' => [],
                'features' => [],
            ]
        );

        // Assigner toutes les données existantes à l'organisation par défaut
        $tables = [
            'reservations',
            'resources',
            'clients',
            'sites',
            'options',
            'biplaceurs',
            'payments',
            'flights',
            'coupons',
            'gift_cards',
        ];

        foreach ($tables as $table) {
            \DB::table($table)
                ->whereNull('organization_id')
                ->update(['organization_id' => $defaultOrg->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Créer une organisation par défaut si elle n'existe pas
        $defaultOrg = \App\Models\Organization::firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default Organization',
                'subscription_tier' => 'free',
                'subscription_status' => 'active',
                'settings' => [],
                'features' => [],
            ]
        );

        // Assigner toutes les données existantes à l'organisation par défaut
        $tables = [
            'reservations',
            'resources',
            'clients',
            'sites',
            'options',
            'biplaceurs',
            'payments',
            'flights',
            'coupons',
            'gift_cards',
        ];

        foreach ($tables as $table) {
            \DB::table($table)
                ->whereNull('organization_id')
                ->update(['organization_id' => $defaultOrg->id]);
        }
    }
};
