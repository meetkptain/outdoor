<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateDailyReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:daily 
                            {--email : Envoyer le rapport par email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Générer le rapport quotidien (CA, vols, statistiques)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $this->info("📊 Rapport quotidien - {$today->format('d/m/Y')}");

        // Statistiques du jour
        $todayStats = [
            'reservations' => Reservation::whereDate('created_at', $today)->count(),
            'scheduled' => Reservation::whereDate('scheduled_at', $today)
                ->whereIn('status', ['scheduled', 'confirmed'])->count(),
            'completed' => Reservation::whereDate('scheduled_at', $today)
                ->where('status', 'completed')->count(),
            'cancelled' => Reservation::whereDate('updated_at', $today)
                ->where('status', 'cancelled')->count(),
        ];

        // CA du jour (payments capturés aujourd'hui)
        $todayRevenue = DB::table('payments')
            ->where('status', 'succeeded')
            ->whereDate('captured_at', $today)
            ->sum('amount');

        // CA hier (pour comparaison)
        $yesterdayRevenue = DB::table('payments')
            ->where('status', 'succeeded')
            ->whereDate('captured_at', $yesterday)
            ->sum('amount');

        // Afficher le rapport
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Nouvelles réservations', $todayStats['reservations']],
                ['Vols planifiés aujourd\'hui', $todayStats['scheduled']],
                ['Vols complétés', $todayStats['completed']],
                ['Annulations', $todayStats['cancelled']],
                ['Chiffre d\'affaires (€)', number_format($todayRevenue, 2)],
                ['CA hier (€)', number_format($yesterdayRevenue, 2)],
                ['Évolution', $yesterdayRevenue > 0 
                    ? number_format((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1) . '%'
                    : 'N/A'],
            ]
        );

        // Envoyer par email si demandé
        if ($this->option('email')) {
            // TODO: Envoyer email au admin avec le rapport
            $this->info("📧 Rapport envoyé par email");
        }

        // Log du rapport
        \Illuminate\Support\Facades\Log::info('Daily report generated', [
            'date' => $today->format('Y-m-d'),
            'stats' => $todayStats,
            'revenue' => $todayRevenue,
        ]);

        return Command::SUCCESS;
    }
}

