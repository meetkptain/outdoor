<?php

namespace App\Providers;

use App\Modules\ModuleInterface;
use App\Modules\ModuleRegistry;
use App\Modules\BaseModule;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Mapping des modules vers leurs classes
     */
    protected array $moduleClasses = [
        'Paragliding' => \App\Modules\Paragliding\ParaglidingModule::class,
        'Surfing' => \App\Modules\Surfing\SurfingModule::class,
    ];

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
                    
                    // Utiliser la classe spécifique du module si disponible, sinon BaseModule
                    $moduleClass = $this->moduleClasses[$moduleName] ?? BaseModule::class;
                    
                    if (!class_exists($moduleClass)) {
                        // Fallback sur BaseModule si la classe n'existe pas
                        $moduleClass = BaseModule::class;
                    }
                    
                    /** @var ModuleInterface $module */
                    $module = new $moduleClass($config);
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
