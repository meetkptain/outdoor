<?php

namespace App\Modules\Surfing\Services;

use App\Models\Organization;
use App\Models\Resource;
use App\Modules\Surfing\Models\SurfingSession;
use Illuminate\Support\Collection;

/**
 * Service pour gérer l'équipement de surf
 */
class EquipmentService
{
    /**
     * Récupérer l'équipement disponible pour une organisation
     */
    public function getAvailableEquipment(Organization $organization, string $date, string $time): Collection
    {
        $allEquipment = Resource::where('organization_id', $organization->id)
            ->where('type', 'equipment')
            ->where('is_active', true)
            ->get();
        
        return $allEquipment->filter(function ($equipment) use ($date, $time) {
            $specs = is_array($equipment->specifications) 
                ? $equipment->specifications 
                : (json_decode($equipment->specifications ?? '{}', true) ?? []);
            $isSurfing = ($specs['category'] ?? '') === 'surfing';
            return $isSurfing && $this->isEquipmentAvailable($equipment, $date, $time);
        });
    }

    /**
     * Vérifier si un équipement est disponible
     */
    public function isEquipmentAvailable(Resource $equipment, string $date, string $time): bool
    {
        // Vérifier les réservations existantes pour cet équipement
        $sessions = SurfingSession::where('scheduled_at', '>=', $date . ' ' . $time)
            ->where('scheduled_at', '<', $date . ' ' . date('H:i:s', strtotime($time . ' +1 hour')))
            ->where('status', 'scheduled')
            ->get();

        foreach ($sessions as $session) {
            $equipmentRented = $session->getEquipmentRentedAttribute();
            if (in_array($equipment->name, $equipmentRented)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Réserver un équipement pour une session
     */
    public function reserveEquipment(SurfingSession $session, array $equipmentNames): void
    {
        $organization = $session->organization;
        $equipment = Resource::where('organization_id', $organization->id)
            ->where('type', 'equipment')
            ->whereIn('name', $equipmentNames)
            ->get();

        if ($equipment->count() !== count($equipmentNames)) {
            throw new \Exception('Certains équipements ne sont pas disponibles');
        }

        $session->setEquipmentRented($equipmentNames);
    }

    /**
     * Libérer l'équipement après une session
     */
    public function releaseEquipment(SurfingSession $session): void
    {
        $session->setEquipmentRented([]);
    }

    /**
     * Récupérer le stock d'équipement
     */
    public function getEquipmentStock(Organization $organization): Collection
    {
        return Resource::where('organization_id', $organization->id)
            ->where('type', 'equipment')
            ->get()
            ->filter(function ($equipment) {
                $specs = is_array($equipment->specifications) 
                    ? $equipment->specifications 
                    : (json_decode($equipment->specifications ?? '{}', true) ?? []);
                return ($specs['category'] ?? '') === 'surfing';
            });
    }
}
