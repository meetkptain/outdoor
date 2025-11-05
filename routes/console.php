<?php

use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\SendRemindersCommand;
use App\Console\Commands\CheckExpiredAuthorizationsCommand;
use App\Console\Commands\CleanupOldDataCommand;
use App\Console\Commands\GenerateDailyReportCommand;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| Tâches planifiées (Scheduler Laravel)
| Exécutées via: php artisan schedule:run (dans cron)
|
*/

// Rappels automatiques 24h avant les vols
Schedule::command(SendRemindersCommand::class)
    ->dailyAt('08:00')
    ->description('Envoyer les rappels 24h avant les vols');

// Vérification autorisations expirées (toutes les heures)
Schedule::command(CheckExpiredAuthorizationsCommand::class)
    ->hourly()
    ->description('Vérifier les autorisations Stripe expirées');

// Nettoyage données anciennes (hebdomadaire, dimanche à 2h du matin)
Schedule::command(CleanupOldDataCommand::class, ['--days' => 365])
    ->weeklyOn(0, '02:00')
    ->description('Nettoyer les anciennes données');

// Rapport quotidien (tous les jours à 20h)
Schedule::command(GenerateDailyReportCommand::class, ['--email'])
    ->dailyAt('20:00')
    ->description('Générer et envoyer le rapport quotidien');

