<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    protected ClientService $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * Liste des clients (admin)
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

        $clients = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Détails d'un client (admin)
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
     * Créer un client (admin)
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
     * Modifier un client (admin)
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
     * Historique d'un client (admin)
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

