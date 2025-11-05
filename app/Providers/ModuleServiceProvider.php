<?php

namespace App\Providers;

use App\Modules\Module;
use App\Modules\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $registry = new ModuleRegistry();
        
        // Charger les modules disponibles
        $modulesPath = app_path('Modules');
        if (is_dir($modulesPath)) {
            $moduleDirs = array_filter(glob($modulesPath . '/*'), 'is_dir');
            
            foreach ($moduleDirs as $moduleDir) {
                $moduleName = basename($moduleDir);
                $configPath = $moduleDir . '/config.php';
                
                if (file_exists($configPath)) {
                    $config = require $configPath;
                    $module = new Module($config);
                    $registry->register($module);
                }
            }
        }
        
        $this->app->instance(ModuleRegistry::class, $registry);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
