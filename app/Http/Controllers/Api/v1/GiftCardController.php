<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Services\ClientService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Gift Cards")
 */
class GiftCardController extends Controller
{
    protected ClientService $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/giftcards/validate",
     *     summary="Valider un bon cadeau",
     *     description="Vérifie la validité d'un bon cadeau et retourne son solde",
     *     operationId="validateGiftCard",
     *     tags={"Gift Cards"},
     *     security={{"organization": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="GIFT123456789", maxLength=50)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bon cadeau valide",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="GIFT123456789"),
     *                 @OA\Property(property="balance", type="number", format="float", example=100.00),
     *                 @OA\Property(property="remaining_amount", type="number", format="float", example=100.00),
     *                 @OA\Property(property="valid_until", type="string", format="date", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bon cadeau invalide ou expiré"),
     *     @OA\Response(response=404, description="Bon cadeau introuvable"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
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
     * @OA\Get(
     *     path="/api/v1/admin/giftcards",
     *     summary="Liste des bons cadeaux (Admin)",
     *     description="Retourne la liste des bons cadeaux avec filtres et pagination",
     *     operationId="listGiftCards",
     *     tags={"Gift Cards"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean"), description="Filtrer par statut actif"),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="Recherche par code"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15), description="Nombre d'éléments par page"),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des bons cadeaux",
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
     * @OA\Post(
     *     path="/api/v1/admin/giftcards",
     *     summary="Créer un bon cadeau (Admin)",
     *     description="Crée un nouveau bon cadeau",
     *     operationId="createGiftCard",
     *     tags={"Gift Cards"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"value"},
     *             @OA\Property(property="code", type="string", nullable=true, example="GIFT123456789", maxLength=50, description="Code personnalisé (généré automatiquement si omis)"),
     *             @OA\Property(property="value", type="number", format="float", example=100.00, minimum=0),
     *             @OA\Property(property="valid_until", type="string", format="date", nullable=true, example="2025-12-31"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Bon cadeau créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bon cadeau créé avec succès"),
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
     * @OA\Put(
     *     path="/api/v1/admin/giftcards/{id}",
     *     summary="Modifier un bon cadeau (Admin)",
     *     description="Met à jour un bon cadeau existant",
     *     operationId="updateGiftCard",
     *     tags={"Gift Cards"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string", example="GIFT123456789", maxLength=50),
     *             @OA\Property(property="valid_until", type="string", format="date", nullable=true, example="2025-12-31"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bon cadeau mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bon cadeau mis à jour avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Bon cadeau non trouvé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
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

