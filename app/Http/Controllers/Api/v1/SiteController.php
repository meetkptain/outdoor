<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Traits\PaginatesApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Sites")
 */
class SiteController extends Controller
{
    use PaginatesApiResponse;
    /**
     * @OA\Get(
     *     path="/api/v1/sites",
     *     summary="Liste des sites",
     *     description="Retourne la liste des sites de décollage/activité avec filtres",
     *     operationId="listSites",
     *     tags={"Sites"},
     *     security={{"organization": {}}},
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean"), description="Filtrer par statut actif (admin uniquement)"),
     *     @OA\Parameter(name="difficulty_level", in="query", @OA\Schema(type="string", enum={"beginner", "intermediate", "advanced"}), description="Filtrer par niveau de difficulté"),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="Recherche (nom, code, localisation)"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15), description="Nombre d'éléments par page"),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des sites",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
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

        $sites = $this->paginateQuery($query->orderBy('name'), $request, 15);

        return $this->paginatedResponse($sites);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sites/{id}",
     *     summary="Détails d'un site",
     *     description="Retourne les détails complets d'un site",
     *     operationId="getSite",
     *     tags={"Sites"},
     *     security={{"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du site",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Site non trouvé ou non disponible"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
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
     * @OA\Post(
     *     path="/api/v1/admin/sites",
     *     summary="Créer un site (Admin)",
     *     description="Crée un nouveau site de décollage/activité",
     *     operationId="createSite",
     *     tags={"Sites"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "name", "location", "latitude", "longitude", "altitude", "difficulty_level"},
     *             @OA\Property(property="code", type="string", example="SITE001", maxLength=50),
     *             @OA\Property(property="name", type="string", example="Col du Poutou", maxLength=255),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="location", type="string", example="Luchon, France", maxLength=255),
     *             @OA\Property(property="latitude", type="number", format="float", example=42.7896, minimum=-90, maximum=90),
     *             @OA\Property(property="longitude", type="number", format="float", example=0.5937, minimum=-180, maximum=180),
     *             @OA\Property(property="altitude", type="integer", example=1800, minimum=0),
     *             @OA\Property(property="difficulty_level", type="string", enum={"beginner", "intermediate", "advanced"}, example="intermediate"),
     *             @OA\Property(property="orientation", type="string", enum={"N", "NE", "E", "SE", "S", "SW", "W", "NW", "multi"}, nullable=true, example="SW"),
     *             @OA\Property(property="wind_conditions", type="string", nullable=true, maxLength=500),
     *             @OA\Property(property="landing_zone_info", type="string", nullable=true, maxLength=1000),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="seasonal_availability", type="array", nullable=true, @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Site créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Site créé avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
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
     * @OA\Put(
     *     path="/api/v1/admin/sites/{id}",
     *     summary="Modifier un site (Admin)",
     *     description="Met à jour un site existant",
     *     operationId="updateSite",
     *     tags={"Sites"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string", example="SITE001", maxLength=50),
     *             @OA\Property(property="name", type="string", example="Col du Poutou", maxLength=255),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="location", type="string", example="Luchon, France", maxLength=255),
     *             @OA\Property(property="latitude", type="number", format="float", example=42.7896, minimum=-90, maximum=90),
     *             @OA\Property(property="longitude", type="number", format="float", example=0.5937, minimum=-180, maximum=180),
     *             @OA\Property(property="altitude", type="integer", example=1800, minimum=0),
     *             @OA\Property(property="difficulty_level", type="string", enum={"beginner", "intermediate", "advanced"}, example="intermediate"),
     *             @OA\Property(property="orientation", type="string", enum={"N", "NE", "E", "SE", "S", "SW", "W", "NW", "multi"}, nullable=true),
     *             @OA\Property(property="wind_conditions", type="string", nullable=true, maxLength=500),
     *             @OA\Property(property="landing_zone_info", type="string", nullable=true, maxLength=1000),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="seasonal_availability", type="array", nullable=true, @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Site mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Site mis à jour avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Site non trouvé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
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
     * @OA\Delete(
     *     path="/api/v1/admin/sites/{id}",
     *     summary="Supprimer un site (Admin)",
     *     description="Supprime un site (désactive si utilisé dans des réservations)",
     *     operationId="deleteSite",
     *     tags={"Sites"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Site supprimé ou désactivé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Site supprimé avec succès")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Site non trouvé"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
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
