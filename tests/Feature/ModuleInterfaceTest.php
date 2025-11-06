<?php

namespace Tests\Feature;

use App\Modules\BaseModule;
use App\Modules\ModuleInterface;
use App\Modules\ModuleHook;
use App\Modules\ModuleRegistry;
use App\Models\Reservation;
use App\Models\ActivitySession;
use App\Models\Activity;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_module_implements_interface(): void
    {
        $module = new BaseModule([
            'name' => 'Test Module',
            'activity_type' => 'test',
            'version' => '1.0.0',
        ]);

        $this->assertInstanceOf(ModuleInterface::class, $module);
    }

    public function test_module_interface_methods(): void
    {
        $module = new BaseModule([
            'name' => 'Test Module',
            'activity_type' => 'test',
            'version' => '2.0.0',
            'constraints' => ['weight' => ['min' => 50]],
            'features' => ['feature1' => true],
            'workflow' => ['stages' => ['pending', 'completed']],
            'models' => ['test' => 'TestModel'],
        ]);

        $this->assertEquals('Test Module', $module->getName());
        $this->assertEquals('test', $module->getActivityType());
        $this->assertEquals('2.0.0', $module->getVersion());
        $this->assertIsArray($module->getConfig());
        $this->assertIsArray($module->getConstraints());
        $this->assertIsArray($module->getFeatures());
        $this->assertIsArray($module->getWorkflow());
        $this->assertIsArray($module->getModels());
        $this->assertTrue($module->hasFeature('feature1'));
        $this->assertEquals(['min' => 50], $module->getConstraint('weight'));
    }

    public function test_module_hooks_enum(): void
    {
        $this->assertEquals('before_reservation_create', ModuleHook::BEFORE_RESERVATION_CREATE->value);
        $this->assertEquals('after_reservation_create', ModuleHook::AFTER_RESERVATION_CREATE->value);
        $this->assertEquals('before_session_schedule', ModuleHook::BEFORE_SESSION_SCHEDULE->value);
        $this->assertEquals('after_session_complete', ModuleHook::AFTER_SESSION_COMPLETE->value);
    }

    public function test_module_registry_hooks(): void
    {
        $registry = new ModuleRegistry();
        
        $module = new BaseModule([
            'name' => 'Test',
            'activity_type' => 'test',
        ]);
        
        $registry->register($module);
        
        $hookCalled = false;
        $registry->registerHook(ModuleHook::BEFORE_RESERVATION_CREATE, $module, function ($data) use (&$hookCalled) {
            $hookCalled = true;
            return $data;
        });
        
        $registry->triggerHook(ModuleHook::BEFORE_RESERVATION_CREATE, 'test', ['test' => 'data']);
        
        $this->assertTrue($hookCalled);
    }

    public function test_module_before_reservation_create_hook(): void
    {
        $organization = Organization::factory()->create();
        $activity = Activity::factory()->create([
            'organization_id' => $organization->id,
            'activity_type' => 'paragliding',
        ]);

        $registry = app(ModuleRegistry::class);
        $module = $registry->get('paragliding');
        
        $this->assertNotNull($module);
        
        // Tester que le hook peut modifier les données
        $data = [
            'activity_id' => $activity->id,
            'customer_weight' => 75,
            'customer_height' => 175,
        ];
        
        $modifiedData = $module->beforeReservationCreate($data);
        
        // Les données doivent être retournées (peuvent être modifiées)
        $this->assertIsArray($modifiedData);
    }

    public function test_module_after_reservation_create_hook(): void
    {
        $organization = Organization::factory()->create();
        $activity = Activity::factory()->create([
            'organization_id' => $organization->id,
            'activity_type' => 'paragliding',
        ]);

        $reservation = Reservation::factory()->create([
            'organization_id' => $organization->id,
            'activity_id' => $activity->id,
            'activity_type' => 'paragliding',
        ]);

        $registry = app(ModuleRegistry::class);
        $module = $registry->get('paragliding');
        
        $this->assertNotNull($module);
        
        // Le hook ne doit pas lever d'exception
        $module->afterReservationCreate($reservation);
        
        $this->assertTrue(true); // Si on arrive ici, pas d'exception
    }
}

