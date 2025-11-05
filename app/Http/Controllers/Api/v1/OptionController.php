<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Option;
use Illuminate\Http\Request;

class OptionController extends Controller
{
    /**
     * Liste des options (public)
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
     * Créer une option (admin)
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
     * Modifier une option (admin)
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
     * Supprimer une option (admin)
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

