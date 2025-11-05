<?php

namespace Tests\Unit\Services;

use App\Models\Resource;
use App\Models\Reservation;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected VehicleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VehicleService();
    }

    /**
     * Test récupération capacité navette par défaut
     */
    public function test_returns_default_capacity_when_not_specified(): void
    {
        $vehicle = Resource::factory()->create([
            'type' => 'vehicle',
            'specifications' => [], // Pas de capacité spécifiée
        ]);

        $capacity = $this->service->getMaxCapacity($vehicle);

        $this->assertEquals(9, $capacity); // Capacité par défaut (9 places total)
    }

    /**
     * Test récupération capacité navette depuis spécifications
     */
    public function test_returns_capacity_from_specifications(): void
    {
        $vehicle = Resource::factory()->create([
            'type' => 'vehicle',
            'specifications' => ['max_capacity' => 10],
        ]);

        $capacity = $this->service->getMaxCapacity($vehicle);

        $this->assertEquals(10, $capacity);
    }

    /**
     * Test récupération nombre de passagers maximum (sans chauffeur)
     */
    public function test_returns_max_passengers_without_driver(): void
    {
        $vehicle = Resource::factory()->create([
            'type' => 'vehicle',
            'specifications' => ['max_capacity' => 9],
        ]);

        $maxPassengers = $this->service->getMaxPassengers($vehicle);

        $this->assertEquals(8, $maxPassengers); // 9 - 1 chauffeur = 8 passagers
    }

    /**
     * Test vérification capacité navette - accepte si places disponibles
     */
    public function test_allows_assignment_when_capacity_available(): void
    {
        $vehicle = Resource::factory()->create([
            'type' => 'vehicle',
            'specifications' => ['max_capacity' => 9],
        ]);

        // Aucune autre réservation à cette date/heure
        $scheduledAt = now()->addDay();

        $hasCapacity = $this->service->checkCapacity(
            $vehicle->id,
            $scheduledAt
        );

        $this->assertTrue($hasCapacity);
    }

    /**
     * Test vérification capacité navette - refuse si capacité dépassée
     */
    public function test_rejects_assignment_when_capacity_exceeded(): void
    {
        $vehicle = Resource::factory()->create([
            'type' => 'vehicle',
            'specifications' => ['max_capacity' => 9],
        ]);

        // Créer plusieurs réservations qui occupent toutes les places (8 passagers max)
        for ($i = 0; $i < 8; $i++) {
            Reservation::factory()->create([
                'vehicle_id' => $vehicle->id,
                'participants_count' => 1,
                'status' => 'scheduled',
                'scheduled_at' => now()->addDay()->setTime(10, 0),
            ]);
        }

        $scheduledAt = now()->addDay()->setTime(10, 0);

        $hasCapacity = $this->service->checkCapacity(
            $vehicle->id,
            $scheduledAt
        );

        $this->assertFalse($hasCapacity);
    }

    /**
     * Test calcul places disponibles
     */
    public function test_calculates_available_seats_correctly(): void
    {
        $vehicle = Resource::factory()->create([
            'type' => 'vehicle',
            'specifications' => ['max_capacity' => 9], // 9 places total = 8 passagers + 1 chauffeur
        ]);

        // Créer 3 réservations avec 1 participant chacune (3 passagers au total)
        for ($i = 0; $i < 3; $i++) {
            Reservation::factory()->create([
                'vehicle_id' => $vehicle->id,
                'participants_count' => 1,
                'status' => 'scheduled',
                'scheduled_at' => now()->addDay()->setTime(10, 0),
            ]);
        }

        $scheduledAt = now()->addDay()->setTime(10, 0);

        $availableSeats = $this->service->getAvailableSeats(
            $vehicle->id,
            $scheduledAt
        );

        // 8 passagers max - 3 déjà assignés = 5 disponibles
        $this->assertEquals(5, $availableSeats);
    }
}

