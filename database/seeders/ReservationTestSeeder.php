<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Reservation;
use App\Models\Option;
use App\Models\Site;
use App\Models\User;
use App\Models\Flight;
use Carbon\Carbon;

class ReservationTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer quelques sites si nécessaire
        $site = Site::firstOrCreate(
            ['code' => 'SITE001'],
            [
                'name' => 'Site de Décollage Principal',
                'location' => 'Montagne de Test',
                'latitude' => 45.123456,
                'longitude' => 5.654321,
                'altitude' => 1200,
                'difficulty_level' => 'beginner',
                'is_active' => true,
            ]
        );

        // Créer quelques options si nécessaire
        $photoOption = Option::firstOrCreate(
            ['code' => 'PHOTO'],
            [
                'name' => 'Pack Photo',
                'description' => 'Photos professionnelles de votre vol',
                'type' => 'photo',
                'price' => 29.90,
                'is_active' => true,
                'is_upsellable' => true,
            ]
        );

        $videoOption = Option::firstOrCreate(
            ['code' => 'VIDEO'],
            [
                'name' => 'Pack Vidéo',
                'description' => 'Vidéo HD de votre vol',
                'type' => 'video',
                'price' => 49.90,
                'is_active' => true,
                'is_upsellable' => true,
            ]
        );

        // Créer une réservation de test
        $reservation = Reservation::create([
            'customer_email' => 'test@example.com',
            'customer_phone' => '+33612345678',
            'customer_first_name' => 'Jean',
            'customer_last_name' => 'Dupont',
            'customer_birth_date' => '1990-01-15',
            'customer_weight' => 75,
            'flight_type' => 'tandem',
            'participants_count' => 1,
            'status' => 'pending',
            'base_amount' => 120.00,
            'options_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 120.00,
            'deposit_amount' => 36.00, // 30% de 120
            'authorized_amount' => 120.00,
            'payment_status' => 'authorized',
            'payment_type' => 'both',
            'site_id' => $site->id,
        ]);

        // Créer un vol associé
        Flight::create([
            'reservation_id' => $reservation->id,
            'participant_first_name' => 'Jean',
            'participant_last_name' => 'Dupont',
            'participant_birth_date' => '1990-01-15',
            'participant_weight' => 75,
            'status' => 'pending',
        ]);

        $this->command->info("✅ Réservation de test créée:");
        $this->command->info("   UUID: {$reservation->uuid}");
        $this->command->info("   Email: {$reservation->customer_email}");
        $this->command->info("   Montant total: {$reservation->total_amount} €");

        // Créer une réservation avec date assignée
        $assignedReservation = Reservation::create([
            'customer_email' => 'assigned@example.com',
            'customer_phone' => '+33612345679',
            'customer_first_name' => 'Marie',
            'customer_last_name' => 'Martin',
            'customer_birth_date' => '1985-05-20',
            'customer_weight' => 65,
            'flight_type' => 'tandem',
            'participants_count' => 1,
            'status' => 'assigned',
            'scheduled_at' => Carbon::now()->addDays(3)->setTime(14, 0),
            'base_amount' => 120.00,
            'options_amount' => 29.90,
            'discount_amount' => 0,
            'total_amount' => 149.90,
            'deposit_amount' => 36.00,
            'authorized_amount' => 149.90,
            'payment_status' => 'authorized',
            'payment_type' => 'both',
            'site_id' => $site->id,
        ]);

        // Ajouter l'option photo
        $assignedReservation->options()->attach($photoOption->id, [
            'quantity' => 1,
            'unit_price' => 29.90,
            'total_price' => 29.90,
            'added_at_stage' => 'initial',
            'added_at' => now(),
        ]);

        Flight::create([
            'reservation_id' => $assignedReservation->id,
            'participant_first_name' => 'Marie',
            'participant_last_name' => 'Martin',
            'participant_birth_date' => '1985-05-20',
            'participant_weight' => 65,
            'status' => 'pending',
        ]);

        $this->command->info("✅ Réservation avec date assignée créée:");
        $this->command->info("   UUID: {$assignedReservation->uuid}");
        $this->command->info("   Email: {$assignedReservation->customer_email}");
        $this->command->info("   Date: {$assignedReservation->scheduled_at->format('d/m/Y à H:i')}");
        $this->command->info("   Montant total: {$assignedReservation->total_amount} €");
    }
}
