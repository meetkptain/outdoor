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
        // Ajouter une colonne instructor_id à la table biplaceurs pour la liaison
        Schema::table('biplaceurs', function (Blueprint $table) {
            $table->foreignId('instructor_id')->nullable()->after('id')->constrained('instructors')->nullOnDelete();
        });

        // Migrer les données existantes de biplaceurs vers instructors
        $biplaceurs = DB::table('biplaceurs')->get();

        foreach ($biplaceurs as $biplaceur) {
            // Décoder les certifications si c'est un JSON string
            $certifications = $biplaceur->certifications;
            if (is_string($certifications)) {
                $certifications = json_decode($certifications, true);
            }

            // Créer l'instructor correspondant
            $instructorId = DB::table('instructors')->insertGetId([
                'organization_id' => $biplaceur->organization_id ?? DB::table('organizations')->where('slug', 'default')->value('id'),
                'user_id' => $biplaceur->user_id,
                'activity_types' => json_encode(['paragliding']),
                'license_number' => $biplaceur->license_number,
                'certifications' => json_encode($certifications ?? []),
                'experience_years' => $biplaceur->experience_years,
                'availability' => $biplaceur->availability,
                'max_sessions_per_day' => $biplaceur->max_flights_per_day,
                'can_accept_instant_bookings' => false,
                'is_active' => $biplaceur->is_active,
                'metadata' => json_encode([
                    'total_flights' => $biplaceur->total_flights ?? 0,
                    'can_tap_to_pay' => $biplaceur->can_tap_to_pay ?? false,
                    'stripe_terminal_location_id' => $biplaceur->stripe_terminal_location_id,
                ]),
                'created_at' => $biplaceur->created_at,
                'updated_at' => $biplaceur->updated_at,
            ]);

            // Mettre à jour le biplaceur avec l'instructor_id
            DB::table('biplaceurs')
                ->where('id', $biplaceur->id)
                ->update(['instructor_id' => $instructorId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer la colonne instructor_id
        Schema::table('biplaceurs', function (Blueprint $table) {
            $table->dropForeign(['instructor_id']);
            $table->dropColumn('instructor_id');
        });

        // Supprimer les instructors créés pour les biplaceurs
        // Note: Cette migration ne supprime pas les instructors qui ont été créés manuellement
        // On ne peut pas facilement distinguer ceux créés automatiquement de ceux créés manuellement
    }
};
