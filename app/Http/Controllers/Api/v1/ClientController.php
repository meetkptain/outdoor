<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientService;
use App\Traits\PaginatesApiResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Clients")
 */
class ClientController extends Controller
{
    use PaginatesApiResponse;

    protected ClientService $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/clients",
     *     summary="Liste des clients (Admin)",
     *     description="Retourne la liste des clients avec filtres et pagination",
     *     operationId="listClients",
     *     tags={"Clients"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="Recherche (nom, email)"),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean"), description="Filtrer par statut actif"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15), description="Nombre d'éléments par page"),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des clients",
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
        $query = Client::with('user');

        // Filtres
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $clients = $this->paginateQuery($query, $request, 15);

        return $this->paginatedResponse($clients);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/clients/{id}",
     *     summary="Détails d'un client (Admin)",
     *     description="Retourne les détails complets d'un client",
     *     operationId="getClient",
     *     tags={"Clients"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du client",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Client non trouvé"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function show(int $id)
    {
        $client = Client::with(['user', 'reservations'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $client->id,
                'user' => [
                    'id' => $client->user->id,
                    'name' => $client->user->name,
                    'email' => $client->user->email,
                    'phone' => $client->user->phone,
                ],
                'phone' => $client->phone,
                'weight' => $client->weight,
                'height' => $client->height,
                'medical_notes' => $client->medical_notes,
                'notes' => $client->notes,
                'total_sessions' => $client->total_flights, // @deprecated - utiliser total_sessions
                'total_flights' => $client->total_flights, // Rétrocompatibilité
                'total_spent' => $client->total_spent,
                'last_activity_date' => $client->last_flight_date, // @deprecated - utiliser last_activity_date
                'last_flight_date' => $client->last_flight_date, // Rétrocompatibilité
                'is_active' => $client->is_active,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/clients",
     *     summary="Créer un client (Admin)",
     *     description="Crée un nouveau client dans le système",
     *     operationId="createClient",
     *     tags={"Clients"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="password", type="string", format="password", nullable=true, minLength=8),
     *             @OA\Property(property="phone", type="string", nullable=true, example="+33612345678"),
     *             @OA\Property(property="weight", type="integer", nullable=true, example=75, minimum=30, maximum=200),
     *             @OA\Property(property="height", type="integer", nullable=true, example=175, minimum=100, maximum=250),
     *             @OA\Property(property="medical_notes", type="string", nullable=true, maxLength=1000),
     *             @OA\Property(property="notes", type="string", nullable=true, maxLength=1000)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Client créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Client créé avec succès"),
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
            'email' => 'required|email|unique:users,email',
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:20',
            'weight' => 'nullable|integer|min:30|max:200',
            'height' => 'nullable|integer|min:100|max:250',
            'medical_notes' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $client = $this->clientService->createClient([
            'first_name' => explode(' ', $validated['name'])[0] ?? $validated['name'],
            'last_name' => explode(' ', $validated['name'], 2)[1] ?? '',
            'email' => $validated['email'],
            'password' => $validated['password'] ?? \Str::random(16),
            'phone' => $validated['phone'] ?? null,
            'weight' => $validated['weight'] ?? null,
            'height' => $validated['height'] ?? null,
            'medical_notes' => $validated['medical_notes'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Client créé avec succès',
            'data' => [
                'id' => $client->id,
                'user' => $client->user->only(['id', 'name', 'email', 'role']),
            ],
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/clients/{id}",
     *     summary="Modifier un client (Admin)",
     *     description="Met à jour les informations d'un client",
     *     operationId="updateClient",
     *     tags={"Clients"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="phone", type="string", nullable=true, example="+33612345678"),
     *             @OA\Property(property="weight", type="integer", nullable=true, example=75, minimum=30, maximum=200),
     *             @OA\Property(property="height", type="integer", nullable=true, example=175, minimum=100, maximum=250),
     *             @OA\Property(property="medical_notes", type="string", nullable=true, maxLength=1000),
     *             @OA\Property(property="notes", type="string", nullable=true, maxLength=1000),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Client mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Client mis à jour avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Client non trouvé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'phone' => 'nullable|string|max:20',
            'weight' => 'nullable|integer|min:30|max:200',
            'height' => 'nullable|integer|min:100|max:250',
            'medical_notes' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $client = $this->clientService->updateClient($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Client mis à jour avec succès',
            'data' => $client->fresh(['user']),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/clients/{id}/history",
     *     summary="Historique d'un client (Admin)",
     *     description="Retourne l'historique complet des réservations d'un client",
     *     operationId="getClientHistory",
     *     tags={"Clients"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Historique du client",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Reservation"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Client non trouvé"),
     *     @OA\Response(response=403, description="Accès refusé"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
     */
    public function history(int $id)
    {
        $history = $this->clientService->getClientHistory($id);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }
}

