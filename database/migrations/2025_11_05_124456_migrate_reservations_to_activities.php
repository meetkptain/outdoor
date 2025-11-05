<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Pour chaque organisation, créer une activité par défaut si elle n'existe pas
        // et mettre à jour les réservations
        $organizations = DB::table('organizations')->get();
        
        foreach ($organizations as $org) {
            // Récupérer ou créer l'activité paragliding pour cette organisation
            $activity = DB::table('activities')->where('organization_id', $org->id)
                ->where('activity_type', 'paragliding')
                ->first();
            
            if (!$activity) {
                $activityId = DB::table('activities')->insertGetId([
                    'organization_id' => $org->id,
                    'activity_type' => 'paragliding',
                    'name' => 'Parapente',
                    'description' => 'Vol en parapente biplace',
                    'duration_minutes' => 90,
                    'max_participants' => 1,
                    'min_participants' => 1,
                    'constraints_config' => json_encode([
                        'weight' => ['min' => 40, 'max' => 120],
                        'height' => ['min' => 140, 'max' => 250],
                    ]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $activityId = $activity->id;
            }

            // Mettre à jour toutes les réservations de cette organisation
            DB::table('reservations')
                ->where('organization_id', $org->id)
                ->whereNull('activity_type')
                ->update([
                    'activity_type' => 'paragliding',
                    'activity_id' => $activityId,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('reservations')
            ->where('activity_type', 'paragliding')
            ->update([
                'activity_type' => null,
                'activity_id' => null,
            ]);
    }
};

