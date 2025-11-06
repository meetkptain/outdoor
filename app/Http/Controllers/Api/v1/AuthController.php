<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(name="Authentication")
 */
class AuthController extends Controller
{
    protected ClientService $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     summary="Enregistrement d'un nouveau client",
     *     description="Crée un nouveau compte client et retourne un token d'authentification",
     *     operationId="register",
     *     tags={"Authentication"},
     *     security={{"organization": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="phone", type="string", nullable=true, example="+33612345678"),
     *             @OA\Property(property="weight", type="integer", nullable=true, example=75, minimum=30, maximum=200),
     *             @OA\Property(property="height", type="integer", nullable=true, example=175, minimum=100, maximum=250)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Compte créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="client", type="object"),
     *                 @OA\Property(property="token", type="string", example="1|abcdef123456...")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
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
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="Connexion",
     *     description="Authentifie un utilisateur et retourne un token Bearer",
     *     operationId="login",
     *     tags={"Authentication"},
     *     security={{"organization": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="token", type="string", example="1|abcdef123456...")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Identifiants invalides"),
     *     @OA\Response(response=429, description="Rate limit atteint")
     * )
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
    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     summary="Informations de l'utilisateur connecté",
     *     description="Retourne les informations de l'utilisateur authentifié",
     *     operationId="me",
     *     tags={"Authentication"},
     *     security={{"sanctum": {}}, {"organization": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Informations utilisateur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
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

