<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Services\ClientService;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    protected ClientService $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * Valider un bon cadeau (public)
     */
    public function validate(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $giftCard = GiftCard::where('code', $validated['code'])
            ->where('is_active', true)
            ->first();

        if (!$giftCard) {
            return response()->json([
                'success' => false,
                'message' => 'Bon cadeau introuvable',
            ], 404);
        }

        if (!$giftCard->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Bon cadeau invalide ou expiré',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $giftCard->id,
                'code' => $giftCard->code,
                'balance' => $giftCard->balance,
                'remaining_amount' => $giftCard->remaining_amount,
                'valid_until' => $giftCard->valid_until,
            ],
        ]);
    }

    /**
     * Liste des bons cadeaux (admin)
     */
    public function index(Request $request)
    {
        $query = GiftCard::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('code', 'like', "%{$search}%");
        }

        $giftCards = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $giftCards,
        ]);
    }

    /**
     * Créer un bon cadeau (admin)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:gift_cards,code',
            'value' => 'required|numeric|min:0',
            'valid_until' => 'nullable|date|after:now',
            'is_active' => 'boolean',
        ]);

        // Générer un code si non fourni
        if (empty($validated['code'])) {
            $validated['code'] = strtoupper(\Str::random(12));
        }

        $giftCard = GiftCard::create([
            'code' => $validated['code'],
            'value' => $validated['value'],
            'balance' => $validated['value'],
            'valid_until' => $validated['valid_until'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bon cadeau créé avec succès',
            'data' => $giftCard,
        ], 201);
    }

    /**
     * Modifier un bon cadeau (admin)
     */
    public function update(Request $request, int $id)
    {
        $giftCard = GiftCard::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:gift_cards,code,' . $id,
            'valid_until' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        $giftCard->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bon cadeau mis à jour avec succès',
            'data' => $giftCard,
        ]);
    }
}

