<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Copie biplaceur_id vers instructor_id dans les réservations
     */
    public function up(): void
    {
        // Pour chaque réservation qui a un biplaceur_id mais pas d'instructor_id
        $reservations = DB::table('reservations')
            ->whereNotNull('biplaceur_id')
            ->whereNull('instructor_id')
            ->get();

        foreach ($reservations as $reservation) {
            // Récupérer l'instructor_id depuis le biplaceur
            $biplaceur = DB::table('biplaceurs')->where('id', $reservation->biplaceur_id)->first();
            
            if ($biplaceur) {
                // Si le biplaceur a déjà un instructor_id (via migration précédente)
                if ($biplaceur->instructor_id) {
                    DB::table('reservations')
                        ->where('id', $reservation->id)
                        ->update([
                            'instructor_id' => $biplaceur->instructor_id,
                        ]);
                } else {
                    // Si le biplaceur n'a pas encore d'instructor_id, créer l'instructor correspondant
                    $instructorId = DB::table('instructors')->insertGetId([
                        'organization_id' => $reservation->organization_id ?? DB::table('organizations')->where('slug', 'default')->value('id'),
                        'user_id' => $biplaceur->user_id,
                        'activity_types' => json_encode(['paragliding']),
                        'license_number' => $biplaceur->license_number,
                        'certifications' => json_encode(json_decode($biplaceur->certifications ?? '[]', true) ?? []),
                        'experience_years' => $biplaceur->experience_years,
                        'availability' => $biplaceur->availability,
                        'max_sessions_per_day' => $biplaceur->max_flights_per_day ?? 5,
                        'can_accept_instant_bookings' => false,
                        'is_active' => $biplaceur->is_active ?? true,
                        'metadata' => json_encode([
                            'total_flights' => $biplaceur->total_flights ?? 0,
                            'can_tap_to_pay' => $biplaceur->can_tap_to_pay ?? false,
                            'stripe_terminal_location_id' => $biplaceur->stripe_terminal_location_id ?? null,
                        ]),
                        'created_at' => $biplaceur->created_at ?? now(),
                        'updated_at' => $biplaceur->updated_at ?? now(),
                    ]);

                    // Mettre à jour le biplaceur avec l'instructor_id
                    DB::table('biplaceurs')
                        ->where('id', $biplaceur->id)
                        ->update(['instructor_id' => $instructorId]);

                    // Mettre à jour la réservation
                    DB::table('reservations')
                        ->where('id', $reservation->id)
                        ->update([
                            'instructor_id' => $instructorId,
                        ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ne peut pas vraiment restaurer car on ne sait pas si instructor_id vient de biplaceur_id
        // On laisse les données telles quelles
    }
};
