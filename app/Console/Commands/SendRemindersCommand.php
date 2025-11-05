<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send 
                            {--hours=24 : Nombre d\'heures avant le vol pour envoyer le rappel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoyer les rappels automatiques 24h avant les vols';

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $this->info("Envoi des rappels pour les vols dans {$hours} heures...");

        // Calculer la plage de temps (vols qui commencent dans X heures)
        $startTime = Carbon::now()->addHours($hours)->startOfHour();
        $endTime = Carbon::now()->addHours($hours + 1)->startOfHour();

        // Récupérer les réservations qui ont un vol dans cette plage
        $reservations = Reservation::whereIn('status', ['scheduled', 'confirmed'])
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$startTime, $endTime])
            ->where('reminder_sent', false)
            ->with(['options', 'site', 'biplaceur.user'])
            ->get();

        $count = 0;
        $errors = 0;

        foreach ($reservations as $reservation) {
            try {
                // Envoyer le rappel
                $this->notificationService->sendReminder($reservation);

                // Marquer comme envoyé
                $reservation->update([
                    'reminder_sent' => true,
                    'reminder_sent_at' => Carbon::now(),
                ]);

                $count++;
                $this->info("✅ Rappel envoyé pour réservation #{$reservation->uuid}");

            } catch (\Exception $e) {
                $errors++;
                $this->error("❌ Erreur pour réservation #{$reservation->uuid}: {$e->getMessage()}");
            }
        }

        $this->info("\n📊 Résumé: {$count} rappels envoyés, {$errors} erreurs");

        return Command::SUCCESS;
    }
}

