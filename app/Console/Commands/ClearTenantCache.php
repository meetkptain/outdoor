<?php

namespace App\Console\Commands;

use App\Helpers\CacheHelper;
use App\Models\Organization;
use Illuminate\Console\Command;

/**
 * Commande pour vider le cache d'un tenant spécifique
 * 
 * Usage: php artisan cache:clear-tenant {organization_id}
 */
class ClearTenantCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-tenant 
                            {organization_id : ID de l\'organisation}
                            {--all : Vider le cache de toutes les organisations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vider le cache d\'une organisation spécifique (tenant)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->clearAllTenants();
        }

        $organizationId = (int) $this->argument('organization_id');

        // Vérifier que l'organisation existe
        $organization = Organization::find($organizationId);
        if (!$organization) {
            $this->error("Organisation #{$organizationId} introuvable.");
            return Command::FAILURE;
        }

        $this->info("Vidage du cache pour l'organisation: {$organization->name} (#{$organizationId})...");

        $deleted = CacheHelper::clearTenant($organizationId);

        if ($deleted > 0) {
            $this->info("✓ Cache vidé avec succès ({$deleted} clés supprimées).");
        } else {
            $this->warn("⚠ Aucune clé de cache trouvée ou suppression non supportée.");
            $this->warn("   Note: La suppression par pattern nécessite Redis avec tags.");
        }

        return Command::SUCCESS;
    }

    /**
     * Vider le cache de toutes les organisations
     */
    protected function clearAllTenants(): int
    {
        if (!$this->confirm('Êtes-vous sûr de vouloir vider le cache de TOUTES les organisations ?')) {
            $this->info('Opération annulée.');
            return Command::SUCCESS;
        }

        $organizations = Organization::all();
        $this->info("Vidage du cache pour {$organizations->count()} organisations...");

        $bar = $this->output->createProgressBar($organizations->count());
        $bar->start();

        $totalDeleted = 0;
        foreach ($organizations as $organization) {
            $deleted = CacheHelper::clearTenant($organization->id);
            $totalDeleted += $deleted;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Cache vidé pour toutes les organisations ({$totalDeleted} clés supprimées au total).");

        return Command::SUCCESS;
    }
}

