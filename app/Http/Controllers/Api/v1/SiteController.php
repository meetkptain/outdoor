<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SiteController extends Controller
{
    /**
     * Liste des sites (public)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Site::query();

        // Filtrer par statut actif (public voit seulement les actifs)
        if (!$request->user() || !$request->user()->isAdmin()) {
            $query->where('is_active', true);
        } elseif ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filtrer par niveau de difficulté
        if ($request->has('difficulty_level')) {
            $query->where('difficulty_level', $request->get('difficulty_level'));
        }

        // Recherche par nom ou code
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $sites = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $sites,
        ]);
    }

    /**
     * Détails d'un site (public)
     */
    public function show(int $id): JsonResponse
    {
        $site = Site::findOrFail($id);

        // Vérifier que le site est actif (sauf admin)
        $user = request()->user();
        if (!$user || $user->role !== 'admin') {
            if (!$site->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Site non disponible',
                ], 404);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $site,
        ]);
    }

    /**
     * Créer un site (admin)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:sites,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'altitude' => 'required|integer|min:0',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'orientation' => 'nullable|in:N,NE,E,SE,S,SW,W,NW,multi',
            'wind_conditions' => 'nullable|string|max:500',
            'landing_zone_info' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'seasonal_availability' => 'nullable|array',
        ]);

        $site = Site::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Site créé avec succès',
            'data' => $site,
        ], 201);
    }

    /**
     * Modifier un site (admin)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $site = Site::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:sites,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'location' => 'sometimes|string|max:255',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'altitude' => 'sometimes|integer|min:0',
            'difficulty_level' => 'sometimes|in:beginner,intermediate,advanced',
            'orientation' => 'nullable|in:N,NE,E,SE,S,SW,W,NW,multi',
            'wind_conditions' => 'nullable|string|max:500',
            'landing_zone_info' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'seasonal_availability' => 'nullable|array',
        ]);

        $site->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Site mis à jour avec succès',
            'data' => $site->fresh(),
        ]);
    }

    /**
     * Supprimer un site (admin)
     */
    public function destroy(int $id): JsonResponse
    {
        $site = Site::findOrFail($id);

        // Vérifier si le site est utilisé dans des réservations
        $hasReservations = $site->reservations()->exists();
        
        if ($hasReservations) {
            // Soft delete : désactiver plutôt que supprimer
            $site->update(['is_active' => false]);
            
            return response()->json([
                'success' => true,
                'message' => 'Site désactivé (utilisé dans des réservations)',
            ]);
        }

        $site->delete();

        return response()->json([
            'success' => true,
            'message' => 'Site supprimé avec succès',
        ]);
    }
}
