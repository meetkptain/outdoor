<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ajouter une colonne activity_session_id à la table flights pour la liaison
        Schema::table('flights', function (Blueprint $table) {
            $table->foreignId('activity_session_id')->nullable()->after('id')->constrained('activity_sessions')->nullOnDelete();
        });

        // Créer une activité par défaut pour le parapente si elle n'existe pas
        $defaultOrg = DB::table('organizations')->where('slug', 'default')->first();
        
        if ($defaultOrg) {
            // Créer une activité par défaut pour chaque organisation
            $organizations = DB::table('organizations')->get();
            
            foreach ($organizations as $org) {
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
                        'metadata' => json_encode([
                            'features' => [
                                'shuttles' => true,
                                'weather_dependent' => true,
                                'rotation_duration' => 90,
                                'max_shuttle_capacity' => 9,
                            ],
                        ]),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $activityId = $activity->id;
                }

                // Migrer les flights de cette organisation
                $flights = DB::table('flights')
                    ->where('organization_id', $org->id)
                    ->get();

                foreach ($flights as $flight) {
                    // Récupérer l'instructor_id depuis la réservation (biplaceur_id ou instructor_id)
                    $reservation = DB::table('reservations')->where('id', $flight->reservation_id)->first();
                    $instructorId = null;
                    
                    if ($reservation) {
                        // Priorité: instructor_id (si déjà migré) sinon biplaceur_id
                        if ($reservation->instructor_id) {
                            $instructorId = $reservation->instructor_id;
                        } elseif ($reservation->biplaceur_id) {
                            $biplaceur = DB::table('biplaceurs')->where('id', $reservation->biplaceur_id)->first();
                            if ($biplaceur && $biplaceur->instructor_id) {
                                $instructorId = $biplaceur->instructor_id;
                            }
                        }
                    }

                    // Créer l'activity_session correspondant
                    $sessionId = DB::table('activity_sessions')->insertGetId([
                        'organization_id' => $flight->organization_id ?? $org->id,
                        'activity_id' => $activityId,
                        'reservation_id' => $flight->reservation_id,
                        'scheduled_at' => $flight->flight_date ?? now(),
                        'duration_minutes' => $flight->duration_minutes,
                        'instructor_id' => $instructorId,
                        'site_id' => $reservation->site_id ?? null,
                        'status' => $this->mapFlightStatus($flight->status),
                        'metadata' => json_encode([
                            'participant_first_name' => $flight->participant_first_name,
                            'participant_last_name' => $flight->participant_last_name,
                            'participant_birth_date' => $flight->participant_birth_date,
                            'participant_weight' => $flight->participant_weight,
                            'max_altitude' => $flight->max_altitude,
                            'flight_notes' => $flight->flight_notes,
                            'photo_included' => $flight->photo_included,
                            'video_included' => $flight->video_included,
                            'photo_url' => $flight->photo_url,
                            'video_url' => $flight->video_url,
                        ]),
                        'created_at' => $flight->created_at,
                        'updated_at' => $flight->updated_at,
                    ]);

                    // Mettre à jour le flight avec l'activity_session_id
                    DB::table('flights')
                        ->where('id', $flight->id)
                        ->update(['activity_session_id' => $sessionId]);
                }
            }
        }
    }

    /**
     * Mapper le statut de flight vers activity_session
     */
    protected function mapFlightStatus(string $flightStatus): string
    {
        return match($flightStatus) {
            'pending' => 'scheduled',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'scheduled',
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer la colonne activity_session_id
        Schema::table('flights', function (Blueprint $table) {
            $table->dropForeign(['activity_session_id']);
            $table->dropColumn('activity_session_id');
        });

        // Supprimer les activity_sessions créés pour les flights
        // Note: Cette migration ne supprime pas les activity_sessions créés manuellement
    }
};
