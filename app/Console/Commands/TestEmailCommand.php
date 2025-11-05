<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Mail\ReservationConfirmationMail;
use App\Mail\AssignmentNotificationMail;
use App\Mail\ReminderMail;
use App\Mail\UpsellAfterFlightMail;
use App\Mail\ThankYouMail;
use App\Mail\OptionsAddedMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email 
                            {type : Type d\'email à tester (confirmation, assignment, reminder, upsell, thank-you, options-added)}
                            {--uuid= : UUID de la réservation à utiliser}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tester l\'envoi d\'emails de réservation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');
        $uuid = $this->option('uuid');

        // Récupérer une réservation
        if ($uuid) {
            $reservation = Reservation::where('uuid', $uuid)->first();
            if (!$reservation) {
                $this->error("Réservation avec UUID {$uuid} non trouvée.");
                return 1;
            }
        } else {
            $reservation = Reservation::first();
            if (!$reservation) {
                $this->error("Aucune réservation trouvée dans la base de données.");
                $this->info("Créez d'abord une réservation ou utilisez l'option --uuid pour spécifier une réservation.");
                return 1;
            }
        }

        $this->info("Utilisation de la réservation: #{$reservation->uuid}");

        // Demander l'email de destination
        $email = $this->ask('Email de destination', $reservation->customer_email);

        // Envoyer l'email selon le type
        try {
            switch ($type) {
                case 'confirmation':
                    Mail::to($email)->send(new ReservationConfirmationMail($reservation));
                    $this->info("✅ Email de confirmation envoyé à {$email}");
                    break;

                case 'assignment':
                    if (!$reservation->scheduled_at) {
                        $this->warn("⚠️  Aucune date assignée. La réservation sera affichée sans date.");
                    }
                    Mail::to($email)->send(new AssignmentNotificationMail($reservation));
                    $this->info("✅ Email d'assignation envoyé à {$email}");
                    break;

                case 'reminder':
                    if (!$reservation->scheduled_at) {
                        $this->error("❌ La réservation n'a pas de date assignée. Impossible d'envoyer un rappel.");
                        return 1;
                    }
                    Mail::to($email)->send(new ReminderMail($reservation));
                    $this->info("✅ Email de rappel envoyé à {$email}");
                    break;

                case 'upsell':
                    Mail::to($email)->send(new UpsellAfterFlightMail($reservation));
                    $this->info("✅ Email d'upsell envoyé à {$email}");
                    break;

                case 'thank-you':
                    Mail::to($email)->send(new ThankYouMail($reservation));
                    $this->info("✅ Email de remerciement envoyé à {$email}");
                    break;

                case 'options-added':
                    Mail::to($email)->send(new OptionsAddedMail($reservation));
                    $this->info("✅ Email d'options ajoutées envoyé à {$email}");
                    break;

                default:
                    $this->error("❌ Type d'email inconnu: {$type}");
                    $this->info("Types disponibles: confirmation, assignment, reminder, upsell, thank-you, options-added");
                    return 1;
            }

            if (config('mail.default') === 'log') {
                $this->warn("⚠️  Mode LOG activé. Vérifiez storage/logs/laravel.log pour voir l'email.");
            } else {
                $this->info("📧 Email envoyé via " . config('mail.default'));
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de l'envoi: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
