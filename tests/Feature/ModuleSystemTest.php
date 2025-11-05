<?php

namespace Tests\Feature;

use App\Modules\Module;
use App\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_module(): void
    {
        $registry = new ModuleRegistry();
        
        $config = [
            'name' => 'Paragliding',
            'version' => '1.0.0',
            'activity_type' => 'paragliding',
            'constraints' => ['weight' => ['min' => 40]],
        ];
        
        $module = new Module($config);
        $registry->register($module);

        $this->assertTrue($registry->has('paragliding'));
        $this->assertEquals($module, $registry->get('paragliding'));
    }

    public function test_can_get_module_by_type(): void
    {
        $registry = new ModuleRegistry();
        
        $module = new Module([
            'activity_type' => 'surfing',
            'name' => 'Surfing',
        ]);
        
        $registry->register($module);

        $retrieved = $registry->get('surfing');
        $this->assertNotNull($retrieved);
        $this->assertEquals('Surfing', $retrieved->getName());
    }

    public function test_module_can_check_features(): void
    {
        $module = new Module([
            'activity_type' => 'paragliding',
            'features' => [
                'shuttles' => true,
                'weather_dependent' => true,
            ],
        ]);

        $this->assertTrue($module->hasFeature('shuttles'));
        $this->assertFalse($module->hasFeature('equipment_rental'));
    }

    public function test_module_can_get_constraints(): void
    {
        $module = new Module([
            'activity_type' => 'paragliding',
            'constraints' => [
                'weight' => ['min' => 40, 'max' => 120],
            ],
        ]);

        $this->assertEquals(['min' => 40, 'max' => 120], $module->getConstraint('weight'));
        $this->assertNull($module->getConstraint('age'));
    }

    public function test_module_registry_loaded_from_filesystem(): void
    {
        // Le ModuleServiceProvider devrait charger le module Paragliding
        $registry = app(ModuleRegistry::class);
        
        $this->assertTrue($registry->has('paragliding'));
        
        $module = $registry->get('paragliding');
        $this->assertEquals('Paragliding', $module->getName());
        $this->assertEquals('1.0.0', $module->getVersion());
    }
}
