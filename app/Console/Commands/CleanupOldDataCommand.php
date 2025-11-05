<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\Notification;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CleanupOldDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:old-data 
                            {--days=365 : Nombre de jours pour conserver les données}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nettoyer les anciennes données (réservations annulées, notifications anciennes)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Nettoyage des données antérieures au {$cutoffDate->format('Y-m-d')}...");

        // Supprimer les réservations annulées ou remboursées anciennes (> X jours)
        $deletedReservations = Reservation::whereIn('status', ['cancelled', 'refunded'])
            ->where('updated_at', '<', $cutoffDate)
            ->where('deleted_at', null) // Pas déjà soft deleted
            ->delete();

        $this->info("✅ {$deletedReservations} réservations anciennes supprimées");

        // Supprimer les notifications envoyées anciennes (> X jours)
        $deletedNotifications = Notification::where('status', 'sent')
            ->where('sent_at', '<', $cutoffDate)
            ->delete();

        $this->info("✅ {$deletedNotifications} notifications anciennes supprimées");

        // Optionnel: Nettoyer les logs (si table logs existe)
        // \DB::table('logs')->where('created_at', '<', $cutoffDate)->delete();

        $this->info("\n📊 Nettoyage terminé");

        return Command::SUCCESS;
    }
}

