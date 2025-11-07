<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Traits\PaginatesApiResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Coupons")
 */
class CouponController extends Controller
{
    use PaginatesApiResponse;
    /**
     * @OA\Get(
     *     path="/api/v1/admin/coupons",
     *     summary="Liste des coupons (Admin)",
     *     description="Retourne la liste des coupons avec filtres et pagination",
     *     operationId="listCoupons",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean"), description="Filtrer par statut actif"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15), description="Nombre d'éléments par page"),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des coupons",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function index(Request $request)
    {
        $query = Coupon::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $coupons = $this->paginateQuery(
            $query->orderBy('created_at', 'desc'),
            $request,
            15
        );

        return $this->paginatedResponse($coupons);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/coupons",
     *     summary="Créer un coupon (Admin)",
     *     description="Crée un nouveau coupon de réduction",
     *     operationId="createCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "name", "discount_type", "discount_value"},
     *             @OA\Property(property="code", type="string", example="SUMMER2024", maxLength=50),
     *             @OA\Property(property="name", type="string", example="Réduction été 2024", maxLength=255),
     *             @OA\Property(property="description", type="string", nullable=true, maxLength=1000),
     *             @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed"}, example="percentage"),
     *             @OA\Property(property="discount_value", type="number", format="float", example=10.00, minimum=0),
     *             @OA\Property(property="max_discount", type="number", format="float", nullable=true, example=50.00),
     *             @OA\Property(property="min_purchase_amount", type="number", format="float", nullable=true, example=100.00),
     *             @OA\Property(property="valid_from", type="string", format="date", nullable=true, example="2024-06-01"),
     *             @OA\Property(property="valid_until", type="string", format="date", nullable=true, example="2024-08-31"),
     *             @OA\Property(property="usage_limit", type="integer", nullable=true, example=100, minimum=1),
     *             @OA\Property(property="usage_limit_per_user", type="integer", nullable=true, example=1, minimum=1),
     *             @OA\Property(property="applicable_activity_types", type="array", nullable=true, @OA\Items(type="string"), example={"paragliding", "surfing"}),
     *             @OA\Property(property="is_first_time_only", type="boolean", example=false),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Coupon créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupon créé avec succès"),
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
            'code' => 'required|string|max:50|unique:coupons,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'applicable_flight_types' => 'nullable|array', // @deprecated
            'applicable_activity_types' => 'nullable|array', // Nouveau nom
            'is_first_time_only' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Support rétrocompatibilité : convertir applicable_activity_types en applicable_flight_types (nom du champ DB)
        if (!empty($validated['applicable_activity_types']) && empty($validated['applicable_flight_types'])) {
            $validated['applicable_flight_types'] = $validated['applicable_activity_types'];
            unset($validated['applicable_activity_types']); // Ne pas essayer de créer ce champ qui n'existe pas en DB
        }
        
        $coupon = Coupon::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coupon créé avec succès',
            'data' => $coupon,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/coupons/{id}",
     *     summary="Modifier un coupon (Admin)",
     *     description="Met à jour un coupon existant",
     *     operationId="updateCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string", example="SUMMER2024", maxLength=50),
     *             @OA\Property(property="name", type="string", example="Réduction été 2024", maxLength=255),
     *             @OA\Property(property="description", type="string", nullable=true, maxLength=1000),
     *             @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed"}, example="percentage"),
     *             @OA\Property(property="discount_value", type="number", format="float", example=10.00, minimum=0),
     *             @OA\Property(property="max_discount", type="number", format="float", nullable=true, example=50.00),
     *             @OA\Property(property="min_purchase_amount", type="number", format="float", nullable=true, example=100.00),
     *             @OA\Property(property="valid_from", type="string", format="date", nullable=true, example="2024-06-01"),
     *             @OA\Property(property="valid_until", type="string", format="date", nullable=true, example="2024-08-31"),
     *             @OA\Property(property="usage_limit", type="integer", nullable=true, example=100, minimum=1),
     *             @OA\Property(property="usage_limit_per_user", type="integer", nullable=true, example=1, minimum=1),
     *             @OA\Property(property="applicable_activity_types", type="array", nullable=true, @OA\Items(type="string"), example={"paragliding"}),
     *             @OA\Property(property="is_first_time_only", type="boolean", example=false),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Coupon mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupon mis à jour avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Coupon non trouvé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function update(Request $request, int $id)
    {
        $coupon = Coupon::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:coupons,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'discount_type' => 'sometimes|in:percentage,fixed',
            'discount_value' => 'sometimes|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'applicable_flight_types' => 'nullable|array', // @deprecated
            'applicable_activity_types' => 'nullable|array', // Nouveau nom
            'is_first_time_only' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Support rétrocompatibilité : convertir applicable_activity_types en applicable_flight_types (nom du champ DB)
        if (!empty($validated['applicable_activity_types']) && empty($validated['applicable_flight_types'])) {
            $validated['applicable_flight_types'] = $validated['applicable_activity_types'];
            unset($validated['applicable_activity_types']); // Ne pas essayer de mettre à jour ce champ qui n'existe pas en DB
        }
        
        $coupon->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coupon mis à jour avec succès',
            'data' => $coupon,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/coupons/{id}",
     *     summary="Supprimer un coupon (Admin)",
     *     description="Supprime un coupon du système",
     *     operationId="deleteCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Coupon supprimé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupon supprimé avec succès")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Coupon non trouvé"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function destroy(int $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon supprimé avec succès',
        ]);
    }
}

