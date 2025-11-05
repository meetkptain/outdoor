<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ResourceController extends Controller
{
    /**
     * Liste des ressources (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Resource::query();

        // Filtrer par type
        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        // Filtrer par statut actif
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Recherche par nom ou code
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $resources = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $resources,
        ]);
    }

    /**
     * Liste des ressources par type (admin)
     */
    public function vehicles(Request $request): JsonResponse
    {
        $vehicles = Resource::where('type', 'vehicle')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $vehicles,
        ]);
    }

    /**
     * Liste des tandem gliders (admin)
     */
    public function tandemGliders(Request $request): JsonResponse
    {
        $gliders = Resource::where('type', 'tandem_glider')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $gliders,
        ]);
    }

    /**
     * Ressources disponibles pour une date/heure (admin)
     */
    public function available(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:vehicle,tandem_glider,equipment',
            'date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
        ]);

        $date = \Carbon\Carbon::parse($validated['date']);
        $time = isset($validated['time']) && $validated['time'] ? \Carbon\Carbon::parse($validated['time']) : null;

        $resources = Resource::where('type', $validated['type'])
            ->where('is_active', true)
            ->get()
            ->filter(function ($resource) use ($date, $time) {
                return $resource->isAvailable($date, $time);
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $resources,
        ]);
    }

    /**
     * Créer une ressource (admin)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:resources,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:tandem_glider,vehicle,equipment',
            'description' => 'nullable|string',
            'specifications' => 'nullable|array',
            'is_active' => 'boolean',
            'availability_schedule' => 'nullable|array',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'altitude' => 'nullable|integer|min:0',
            'difficulty_level' => 'nullable|in:beginner,intermediate,advanced',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'max_weight' => 'nullable|integer|min:0',
            'last_maintenance_date' => 'nullable|date',
            'next_maintenance_date' => 'nullable|date|after_or_equal:today',
            'maintenance_notes' => 'nullable|string',
        ]);

        $resource = Resource::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ressource créée avec succès',
            'data' => $resource,
        ], 201);
    }

    /**
     * Détails d'une ressource (admin)
     */
    public function show(int $id): JsonResponse
    {
        $resource = Resource::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $resource,
        ]);
    }

    /**
     * Modifier une ressource (admin)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $resource = Resource::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:resources,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:tandem_glider,vehicle,equipment',
            'description' => 'nullable|string',
            'specifications' => 'nullable|array',
            'is_active' => 'boolean',
            'availability_schedule' => 'nullable|array',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'altitude' => 'nullable|integer|min:0',
            'difficulty_level' => 'nullable|in:beginner,intermediate,advanced',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'max_weight' => 'nullable|integer|min:0',
            'last_maintenance_date' => 'nullable|date',
            'next_maintenance_date' => 'nullable|date',
            'maintenance_notes' => 'nullable|string',
        ]);

        $resource->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ressource mise à jour avec succès',
            'data' => $resource->fresh(),
        ]);
    }

    /**
     * Supprimer une ressource (admin)
     */
    public function destroy(int $id): JsonResponse
    {
        $resource = Resource::findOrFail($id);

        // Vérifier si la ressource est utilisée dans des réservations
        $hasReservations = Reservation::where('tandem_glider_id', $resource->id)
            ->orWhere('vehicle_id', $resource->id)
            ->exists();
        
        if ($hasReservations) {
            // Soft delete : désactiver plutôt que supprimer
            $resource->update(['is_active' => false]);
            
            return response()->json([
                'success' => true,
                'message' => 'Ressource désactivée (utilisée dans des réservations)',
            ]);
        }

        $resource->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ressource supprimée avec succès',
        ]);
    }
}
