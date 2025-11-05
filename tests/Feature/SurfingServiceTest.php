<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\Resource;
use App\Modules\Surfing\Models\SurfingSession;
use App\Modules\Surfing\Services\EquipmentService;
use App\Modules\Surfing\Services\TideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurfingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EquipmentService $equipmentService;
    protected TideService $tideService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->equipmentService = new EquipmentService();
        $this->tideService = new TideService();
    }

    public function test_equipment_service_can_get_available_equipment(): void
    {
        $organization = Organization::factory()->create();

        // Créer des équipements de surf
        $surfboard = Resource::create([
            'organization_id' => $organization->id,
            'code' => 'SURFBOARD-001',
            'type' => 'equipment',
            'name' => 'Surfboard',
            'specifications' => json_encode([
                'category' => 'surfing',
                'size' => 'longboard',
            ]),
            'is_active' => true,
        ]);

        $wetsuit = Resource::create([
            'organization_id' => $organization->id,
            'code' => 'WETSUIT-001',
            'type' => 'equipment',
            'name' => 'Wetsuit',
            'specifications' => json_encode([
                'category' => 'surfing',
                'size' => 'medium',
            ]),
            'is_active' => true,
        ]);

        $equipment = $this->equipmentService->getAvailableEquipment(
            $organization,
            now()->format('Y-m-d'),
            '09:00'
        );

        $this->assertGreaterThanOrEqual(2, $equipment->count());
        $this->assertTrue($equipment->contains('name', 'Surfboard'));
        $this->assertTrue($equipment->contains('name', 'Wetsuit'));
    }

    public function test_equipment_service_can_check_availability(): void
    {
        $organization = Organization::factory()->create();
        $activity = Activity::factory()->create([
            'organization_id' => $organization->id,
            'activity_type' => 'surfing',
        ]);

        $equipment = Resource::create([
            'organization_id' => $organization->id,
            'code' => 'SURFBOARD-TEST',
            'type' => 'equipment',
            'name' => 'Surfboard Test',
            'specifications' => json_encode(['category' => 'surfing']),
            'is_active' => true,
        ]);

        $date = now()->format('Y-m-d');
        $time = '10:00';

        // L'équipement devrait être disponible
        $isAvailable = $this->equipmentService->isEquipmentAvailable($equipment, $date, $time);
        $this->assertTrue($isAvailable);

        // Créer une session qui utilise cet équipement
        $session = SurfingSession::create([
            'organization_id' => $organization->id,
            'activity_id' => $activity->id,
            'scheduled_at' => $date . ' ' . $time,
            'status' => 'scheduled',
            'metadata' => json_encode([
                'equipment_rented' => ['Surfboard Test'],
            ]),
        ]);

        // L'équipement ne devrait plus être disponible
        $isAvailable = $this->equipmentService->isEquipmentAvailable($equipment, $date, $time);
        $this->assertFalse($isAvailable);
    }

    public function test_equipment_service_can_reserve_equipment(): void
    {
        $organization = Organization::factory()->create();
        $activity = Activity::factory()->create([
            'organization_id' => $organization->id,
            'activity_type' => 'surfing',
        ]);

        Resource::create([
            'organization_id' => $organization->id,
            'code' => 'SURFBOARD-002',
            'type' => 'equipment',
            'name' => 'Surfboard',
            'specifications' => json_encode(['category' => 'surfing']),
            'is_active' => true,
        ]);

        $session = SurfingSession::create([
            'organization_id' => $organization->id,
            'activity_id' => $activity->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $this->equipmentService->reserveEquipment($session, ['Surfboard']);

        $session->refresh();
        $this->assertEquals(['Surfboard'], $session->getEquipmentRentedAttribute());
    }

    public function test_equipment_service_throws_exception_for_unavailable_equipment(): void
    {
        $organization = Organization::factory()->create();
        $activity = Activity::factory()->create([
            'organization_id' => $organization->id,
            'activity_type' => 'surfing',
        ]);

        $session = SurfingSession::create([
            'organization_id' => $organization->id,
            'activity_id' => $activity->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $this->expectException(\Exception::class);
        $this->equipmentService->reserveEquipment($session, ['NonExistentEquipment']);
    }

    public function test_tide_service_can_get_tide_level(): void
    {
        $date = now()->format('Y-m-d');
        $tideLevel = $this->tideService->getTideLevel($date, '09:00', 'site1');

        $this->assertContains($tideLevel, ['low', 'rising', 'high', 'falling']);
    }

    public function test_tide_service_can_get_high_tide_times(): void
    {
        $date = now()->format('Y-m-d');
        $highTideTimes = $this->tideService->getHighTideTimes($date, 'site1');

        $this->assertIsArray($highTideTimes);
        $this->assertNotEmpty($highTideTimes);
    }

    public function test_tide_service_can_check_compatibility(): void
    {
        $date = now()->format('Y-m-d');
        $time = '09:00';
        $isCompatible = $this->tideService->isSessionCompatibleWithTide($date, $time, 'site1');

        $this->assertIsBool($isCompatible);
    }

    public function test_equipment_service_can_release_equipment(): void
    {
        $organization = Organization::factory()->create();
        $activity = Activity::factory()->create([
            'organization_id' => $organization->id,
            'activity_type' => 'surfing',
        ]);

        $session = SurfingSession::create([
            'organization_id' => $organization->id,
            'activity_id' => $activity->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'metadata' => json_encode([
                'equipment_rented' => ['Surfboard', 'Wetsuit'],
            ]),
        ]);

        $this->equipmentService->releaseEquipment($session);

        $session->refresh();
        $this->assertEmpty($session->getEquipmentRentedAttribute());
    }

    public function test_equipment_service_can_get_stock(): void
    {
        $organization = Organization::factory()->create();

        Resource::create([
            'organization_id' => $organization->id,
            'code' => 'SURFBOARD-003',
            'type' => 'equipment',
            'name' => 'Surfboard 1',
            'specifications' => json_encode(['category' => 'surfing']),
            'is_active' => true,
        ]);

        Resource::create([
            'organization_id' => $organization->id,
            'code' => 'WETSUIT-002',
            'type' => 'equipment',
            'name' => 'Wetsuit 1',
            'specifications' => json_encode(['category' => 'surfing']),
            'is_active' => true,
        ]);

        $stock = $this->equipmentService->getEquipmentStock($organization);

        $this->assertGreaterThanOrEqual(2, $stock->count());
    }
}
