<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected ClientService $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * Enregistrement d'un nouveau client
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'weight' => 'nullable|integer|min:30|max:200',
            'height' => 'nullable|integer|min:100|max:250',
        ]);

        try {
            // Créer le client via le service
            $client = $this->clientService->createClient([
                'first_name' => explode(' ', $validated['name'])[0] ?? $validated['name'],
                'last_name' => explode(' ', $validated['name'], 2)[1] ?? '',
                'email' => $validated['email'],
                'password' => $validated['password'],
                'phone' => $validated['phone'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'height' => $validated['height'] ?? null,
            ]);

            // Générer un token
            $token = $client->user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $client->user->id,
                        'name' => $client->user->name,
                        'email' => $client->user->email,
                        'role' => $client->user->role,
                    ],
                    'client' => [
                        'id' => $client->id,
                        'phone' => $client->phone,
                        'weight' => $client->weight,
                        'height' => $client->height,
                    ],
                    'token' => $token,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du compte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Connexion
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        // Générer un token
        $token = $user->createToken('api-token')->plainTextToken;

        $response = [
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token' => $token,
            ],
        ];

        // Ajouter les données spécifiques selon le rôle
        if ($user->isClient() && $user->client) {
            $response['data']['client'] = [
                'id' => $user->client->id,
                'phone' => $user->client->phone,
                'weight' => $user->client->weight,
                'height' => $user->client->height,
            ];
        }

        // Récupérer l'instructeur si l'utilisateur est instructeur
        $organization = $user->getCurrentOrganization();
        $instructor = $organization ? $user->getInstructorForOrganization($organization) : null;
        if ($instructor) {
            $response['data']['instructor'] = [
                'id' => $instructor->id,
                'license_number' => $instructor->license_number,
                'can_tap_to_pay' => $instructor->metadata['can_tap_to_pay'] ?? false,
                'activity_types' => $instructor->activity_types,
            ];
        }
        
        // Rétrocompatibilité : si l'utilisateur est biplaceur mais n'a pas d'instructor, utiliser biplaceur
        if ($user->isBiplaceur() && $user->biplaceur && !$instructor) {
            $response['data']['biplaceur'] = [
                'id' => $user->biplaceur->id,
                'license_number' => $user->biplaceur->license_number,
                'can_tap_to_pay' => $user->biplaceur->can_tap_to_pay ?? false,
            ];
        }

        return response()->json($response);
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie',
        ]);
    }

    /**
     * Profil utilisateur actuel
     */
    public function me(Request $request)
    {
        $user = $request->user()->load(['client']);

        $response = [
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'phone' => $user->phone,
                ],
            ],
        ];

        // Ajouter les données spécifiques selon le rôle
        if ($user->isClient() && $user->client) {
            $response['data']['client'] = [
                'id' => $user->client->id,
                'phone' => $user->client->phone,
                'weight' => $user->client->weight,
                'height' => $user->client->height,
                'total_sessions' => $user->client->total_flights, // @deprecated - utiliser total_sessions
                'total_flights' => $user->client->total_flights, // Rétrocompatibilité
                'total_spent' => $user->client->total_spent,
                'last_activity_date' => $user->client->last_flight_date, // @deprecated - utiliser last_activity_date
                'last_flight_date' => $user->client->last_flight_date, // Rétrocompatibilité
            ];
        }

        // Récupérer l'instructeur si l'utilisateur est instructeur
        $organization = $user->getCurrentOrganization();
        $instructor = $organization ? $user->getInstructorForOrganization($organization) : null;
        if ($instructor) {
            $response['data']['instructor'] = [
                'id' => $instructor->id,
                'license_number' => $instructor->license_number,
                'experience_years' => $instructor->experience_years,
                'activity_types' => $instructor->activity_types,
                'availability' => $instructor->availability,
                'can_tap_to_pay' => $instructor->metadata['can_tap_to_pay'] ?? false,
            ];
        }
        
        // Rétrocompatibilité : si l'utilisateur est biplaceur mais n'a pas d'instructor, utiliser biplaceur
        if ($user->isBiplaceur() && $user->biplaceur && !$instructor) {
            $response['data']['biplaceur'] = [
                'id' => $user->biplaceur->id,
                'license_number' => $user->biplaceur->license_number,
                'experience_years' => $user->biplaceur->experience_years,
                'total_flights' => $user->biplaceur->total_flights,
                'availability' => $user->biplaceur->availability,
                'can_tap_to_pay' => $user->biplaceur->can_tap_to_pay ?? false,
            ];
        }

        return response()->json($response);
    }
}

