<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Option;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Options")
 */
class OptionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/options",
     *     summary="Liste des options",
     *     description="Retourne la liste des options disponibles (photos, vidéos, etc.)",
     *     operationId="listOptions",
     *     tags={"Options"},
     *     security={{"organization": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des options",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function index(Request $request)
    {
        $options = Option::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($option) {
                return [
                    'id' => $option->id,
                    'name' => $option->name,
                    'description' => $option->description,
                    'price' => $option->price,
                    'price_type' => $option->price_type,
                    'type' => $option->type,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/options",
     *     summary="Créer une option (Admin)",
     *     description="Crée une nouvelle option (photo, vidéo, souvenir, etc.)",
     *     operationId="createOption",
     *     tags={"Options"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "price", "price_type", "type"},
     *             @OA\Property(property="name", type="string", example="Photos HD", maxLength=255),
     *             @OA\Property(property="description", type="string", nullable=true, maxLength=1000),
     *             @OA\Property(property="price", type="number", format="float", example=25.00, minimum=0),
     *             @OA\Property(property="price_type", type="string", enum={"fixed", "per_participant"}, example="fixed"),
     *             @OA\Property(property="type", type="string", enum={"photo", "video", "souvenir", "insurance", "transport", "other"}, example="photo"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Option créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Option créée avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'price_type' => 'required|in:fixed,per_participant',
            'type' => 'required|in:photo,video,souvenir,insurance,transport,other',
            'is_active' => 'boolean',
        ]);

        $option = Option::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Option créée avec succès',
            'data' => $option,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/options/{id}",
     *     summary="Modifier une option (Admin)",
     *     description="Met à jour une option existante",
     *     operationId="updateOption",
     *     tags={"Options"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Photos HD", maxLength=255),
     *             @OA\Property(property="description", type="string", nullable=true, maxLength=1000),
     *             @OA\Property(property="price", type="number", format="float", example=25.00, minimum=0),
     *             @OA\Property(property="price_type", type="string", enum={"fixed", "per_participant"}, example="fixed"),
     *             @OA\Property(property="type", type="string", enum={"photo", "video", "souvenir", "insurance", "transport", "other"}, example="photo"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Option mise à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Option mise à jour avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Option non trouvée"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function update(Request $request, int $id)
    {
        $option = Option::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'sometimes|numeric|min:0',
            'price_type' => 'sometimes|in:fixed,per_participant',
            'type' => 'sometimes|in:photo,video,souvenir,insurance,transport,other',
            'is_active' => 'boolean',
        ]);

        $option->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Option mise à jour avec succès',
            'data' => $option,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/options/{id}",
     *     summary="Supprimer une option (Admin)",
     *     description="Supprime une option du système",
     *     operationId="deleteOption",
     *     tags={"Options"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Option supprimée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Option supprimée avec succès")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Option non trouvée"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function destroy(int $id)
    {
        $option = Option::findOrFail($id);
        $option->delete(); // Soft delete si disponible

        return response()->json([
            'success' => true,
            'message' => 'Option supprimée avec succès',
        ]);
    }
}

