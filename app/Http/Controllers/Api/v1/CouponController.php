<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Liste des coupons (admin)
     */
    public function index(Request $request)
    {
        $query = Coupon::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $coupons = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $coupons,
        ]);
    }

    /**
     * Créer un coupon (admin)
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
            'applicable_flight_types' => 'nullable|array',
            'is_first_time_only' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Support rétrocompatibilité : convertir applicable_flight_types en applicable_activity_types
        if (!empty($validated['applicable_flight_types']) && empty($validated['applicable_activity_types'])) {
            $validated['applicable_activity_types'] = $validated['applicable_flight_types'];
        }
        
        $coupon = Coupon::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coupon créé avec succès',
            'data' => $coupon,
        ], 201);
    }

    /**
     * Modifier un coupon (admin)
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
            'applicable_flight_types' => 'nullable|array',
            'is_first_time_only' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Support rétrocompatibilité : convertir applicable_flight_types en applicable_activity_types
        if (!empty($validated['applicable_flight_types']) && empty($validated['applicable_activity_types'])) {
            $validated['applicable_activity_types'] = $validated['applicable_flight_types'];
        }
        
        $coupon->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coupon mis à jour avec succès',
            'data' => $coupon,
        ]);
    }

    /**
     * Supprimer un coupon (admin)
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

