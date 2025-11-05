<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migre flight_type vers activity_type + activity_id dans les réservations
     */
    public function up(): void
    {
        // Pour chaque organisation, créer des activités par type de vol si nécessaire
        $organizations = DB::table('organizations')->get();
        
        foreach ($organizations as $org) {
            // Récupérer ou créer l'activité paragliding pour cette organisation
            // Tous les flight_type (tandem, biplace, initiation, etc.) sont des variantes de paragliding
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
                    'pricing_config' => json_encode([
                        'tandem' => 120,
                        'biplace' => 120,
                        'initiation' => 150,
                        'perfectionnement' => 180,
                        'autonome' => 200,
                    ]),
                    'constraints_config' => json_encode([
                        'weight' => ['min' => 40, 'max' => 120],
                        'height' => ['min' => 140, 'max' => 250],
                    ]),
                    'metadata' => json_encode([
                        'features' => [
                            'shuttles' => true,
                            'weather_dependent' => true,
                            'rotation_duration' => 90,
                            'max_shuttle_capacity' => 9,
                        ],
                        'flight_types' => ['tandem', 'biplace', 'initiation', 'perfectionnement', 'autonome'],
                    ]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $activityId = $activity->id;
            }

            // Mettre à jour toutes les réservations de cette organisation
            // Si flight_type existe mais activity_id n'existe pas, les migrer
            $reservations = DB::table('reservations')
                ->where('organization_id', $org->id)
                ->whereNotNull('flight_type')
                ->where(function($query) {
                    $query->whereNull('activity_id')
                          ->orWhereNull('activity_type');
                })
                ->get();

            foreach ($reservations as $reservation) {
                // Stocker le flight_type dans metadata pour référence
                $metadata = json_decode($reservation->metadata ?? '{}', true);
                $metadata['original_flight_type'] = $reservation->flight_type;
                
                DB::table('reservations')
                    ->where('id', $reservation->id)
                    ->update([
                        'activity_type' => 'paragliding',
                        'activity_id' => $activityId,
                        'metadata' => json_encode($metadata),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurer flight_type depuis metadata si possible
        $reservations = DB::table('reservations')
            ->whereNotNull('metadata')
            ->get();

        foreach ($reservations as $reservation) {
            $metadata = json_decode($reservation->metadata, true);
            if (isset($metadata['original_flight_type'])) {
                DB::table('reservations')
                    ->where('id', $reservation->id)
                    ->update([
                        'flight_type' => $metadata['original_flight_type'],
                    ]);
            }
        }

        // Mettre activity_type et activity_id à null
        DB::table('reservations')
            ->where('activity_type', 'paragliding')
            ->update([
                'activity_type' => null,
                'activity_id' => null,
            ]);
    }
};
