<?php

namespace App\Modules\Surfing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Organization;
use App\Modules\Surfing\Services\EquipmentService;
use App\Modules\Surfing\Services\TideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurfingController extends Controller
{
    protected EquipmentService $equipmentService;
    protected TideService $tideService;

    public function __construct(EquipmentService $equipmentService, TideService $tideService)
    {
        $this->equipmentService = $equipmentService;
        $this->tideService = $tideService;
    }

    /**
     * Récupérer les disponibilités pour le surf
     */
    public function getAvailability(Request $request): JsonResponse
    {
        $organization = auth()->user()->currentOrganization;
        $date = $request->input('date', now()->format('Y-m-d'));
        $time = $request->input('time');

        $activity = Activity::where('organization_id', $organization->id)
            ->where('activity_type', 'surfing')
            ->first();

        if (!$activity) {
            return response()->json(['error' => 'Surfing activity not found'], 404);
        }

        $availability = [
            'date' => $date,
            'activity' => $activity,
            'equipment_available' => $this->equipmentService->getAvailableEquipment($organization, $date, $time ?? '09:00'),
            'tide_info' => $time ? [
                'level' => $this->tideService->getTideLevel($date, $time, $request->input('site_id')),
                'is_favorable' => $this->tideService->isTideFavorable($date, $time, $request->input('site_id')),
            ] : null,
        ];

        return response()->json($availability);
    }

    /**
     * Récupérer l'équipement disponible
     */
    public function getEquipment(Request $request): JsonResponse
    {
        $organization = auth()->user()->currentOrganization;
        $date = $request->input('date', now()->format('Y-m-d'));
        $time = $request->input('time', '09:00');

        $equipment = $this->equipmentService->getAvailableEquipment($organization, $date, $time);

        return response()->json([
            'equipment' => $equipment,
            'date' => $date,
            'time' => $time,
        ]);
    }

    /**
     * Récupérer les informations de marée
     */
    public function getTideInfo(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $siteId = $request->input('site_id');

        $tideInfo = [
            'date' => $date,
            'level' => $this->tideService->getTideLevel($date, '09:00', $siteId ?? ''),
            'high_tide_times' => $this->tideService->getHighTideTimes($date, $siteId ?? ''),
        ];

        return response()->json($tideInfo);
    }
}

