<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Biplaceur;
use App\Services\BiplaceurService;
use Illuminate\Http\Request;

class BiplaceurController extends Controller
{
    protected BiplaceurService $biplaceurService;

    public function __construct(BiplaceurService $biplaceurService)
    {
        $this->biplaceurService = $biplaceurService;
    }

    /**
     * Liste des biplaceurs (public)
     */
    public function index(Request $request)
    {
        $biplaceurs = Biplaceur::with('user')
            ->where('is_active', true)
            ->get()
            ->map(function ($biplaceur) {
                return [
                    'id' => $biplaceur->id,
                    'name' => $biplaceur->user->name,
                    'experience_years' => $biplaceur->experience_years,
                    'total_flights' => $biplaceur->total_flights,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $biplaceurs,
        ]);
    }

    /**
     * Détails d'un biplaceur (admin)
     */
    public function show(int $id)
    {
        $biplaceur = Biplaceur::with('user', 'reservations')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $biplaceur->id,
                'user' => [
                    'id' => $biplaceur->user->id,
                    'name' => $biplaceur->user->name,
                    'email' => $biplaceur->user->email,
                    'phone' => $biplaceur->user->phone,
                ],
                'license_number' => $biplaceur->license_number,
                'certifications' => $biplaceur->certifications,
                'experience_years' => $biplaceur->experience_years,
                'total_flights' => $biplaceur->total_flights,
                'availability' => $biplaceur->availability,
                'can_tap_to_pay' => $biplaceur->can_tap_to_pay,
                'is_active' => $biplaceur->is_active,
            ],
        ]);
    }

    /**
     * Créer un biplaceur (admin)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'license_number' => 'nullable|string|max:100',
            'certifications' => 'nullable|array',
            'experience_years' => 'nullable|integer|min:0',
            'availability' => 'nullable|array',
            'can_tap_to_pay' => 'boolean',
            'stripe_terminal_location_id' => 'nullable|string',
        ]);

        // Créer l'utilisateur
        $user = \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => 'biplaceur',
            'phone' => $validated['phone'] ?? null,
        ]);

        // Créer le biplaceur
        $biplaceur = Biplaceur::create([
            'user_id' => $user->id,
            'license_number' => $validated['license_number'] ?? null,
            'certifications' => $validated['certifications'] ?? [],
            'experience_years' => $validated['experience_years'] ?? null,
            'availability' => $validated['availability'] ?? null,
            'can_tap_to_pay' => $validated['can_tap_to_pay'] ?? false,
            'stripe_terminal_location_id' => $validated['stripe_terminal_location_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Biplaceur créé avec succès',
            'data' => [
                'id' => $biplaceur->id,
                'user' => $user->only(['id', 'name', 'email', 'role']),
            ],
        ], 201);
    }

    /**
     * Modifier un biplaceur (admin)
     */
    public function update(Request $request, int $id)
    {
        $biplaceur = Biplaceur::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $biplaceur->user_id,
            'phone' => 'nullable|string|max:20',
            'license_number' => 'nullable|string|max:100',
            'certifications' => 'nullable|array',
            'experience_years' => 'nullable|integer|min:0',
            'availability' => 'nullable|array',
            'is_active' => 'boolean',
            'can_tap_to_pay' => 'boolean',
            'stripe_terminal_location_id' => 'nullable|string',
        ]);

        // Mettre à jour l'utilisateur
        if (isset($validated['name']) || isset($validated['email']) || isset($validated['phone'])) {
            $biplaceur->user->update([
                'name' => $validated['name'] ?? $biplaceur->user->name,
                'email' => $validated['email'] ?? $biplaceur->user->email,
                'phone' => $validated['phone'] ?? $biplaceur->user->phone,
            ]);
        }

        // Mettre à jour le biplaceur
        $biplaceur->update([
            'license_number' => $validated['license_number'] ?? $biplaceur->license_number,
            'certifications' => $validated['certifications'] ?? $biplaceur->certifications,
            'experience_years' => $validated['experience_years'] ?? $biplaceur->experience_years,
            'availability' => $validated['availability'] ?? $biplaceur->availability,
            'is_active' => $validated['is_active'] ?? $biplaceur->is_active,
            'can_tap_to_pay' => $validated['can_tap_to_pay'] ?? $biplaceur->can_tap_to_pay,
            'stripe_terminal_location_id' => $validated['stripe_terminal_location_id'] ?? $biplaceur->stripe_terminal_location_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Biplaceur mis à jour avec succès',
            'data' => $biplaceur->fresh(['user']),
        ]);
    }

    /**
     * Supprimer un biplaceur (admin)
     */
    public function destroy(int $id)
    {
        $biplaceur = Biplaceur::findOrFail($id);
        $biplaceur->delete(); // Soft delete

        return response()->json([
            'success' => true,
            'message' => 'Biplaceur supprimé avec succès',
        ]);
    }

    /**
     * Mes vols (biplaceur)
     */
    public function myFlights(Request $request)
    {
        $user = $request->user();
        $biplaceur = $user->biplaceur;

        if (!$biplaceur) {
            return response()->json([
                'success' => false,
                'message' => 'Biplaceur non trouvé',
            ], 404);
        }

        $reservations = $biplaceur->reservations()
            ->with(['client', 'site', 'options'])
            ->orderBy('scheduled_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations,
        ]);
    }

    /**
     * Vols du jour (biplaceur)
     */
    public function flightsToday(Request $request)
    {
        $user = $request->user();
        $biplaceur = $user->biplaceur;

        if (!$biplaceur) {
            return response()->json([
                'success' => false,
                'message' => 'Biplaceur non trouvé',
            ], 404);
        }

        $flights = $this->biplaceurService->getFlightsToday($biplaceur->id);

        return response()->json([
            'success' => true,
            'data' => $flights,
        ]);
    }

    /**
     * Calendrier (biplaceur ou admin)
     */
    public function calendar(Request $request, ?int $id = null)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Si un ID est fourni (route admin), utiliser cet ID
        // Sinon, utiliser le biplaceur de l'utilisateur connecté
        if ($id) {
            $biplaceur = Biplaceur::findOrFail($id);
        } else {
            $user = $request->user();
            $biplaceur = $user->biplaceur;

            if (!$biplaceur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Biplaceur non trouvé',
                ], 404);
            }
        }

        $flights = $this->biplaceurService->getCalendar(
            $biplaceur->id,
            $validated['start_date'],
            $validated['end_date']
        );

        return response()->json([
            'success' => true,
            'data' => $flights,
        ]);
    }

    /**
     * Mettre à jour disponibilités (biplaceur)
     */
    public function updateAvailability(Request $request)
    {
        $validated = $request->validate([
            'availability' => 'required|array',
            'availability.days' => 'required|array',
            'availability.days.*' => 'integer|between:1,7',
            'availability.hours' => 'nullable|array',
            'availability.hours.*' => 'integer|between:0,23',
            'availability.exceptions' => 'nullable|array',
            'availability.exceptions.*' => 'date',
        ]);

        $user = $request->user();
        $biplaceur = $user->biplaceur;

        if (!$biplaceur) {
            return response()->json([
                'success' => false,
                'message' => 'Biplaceur non trouvé',
            ], 404);
        }

        $this->biplaceurService->updateAvailability($biplaceur->id, $validated['availability']);

        return response()->json([
            'success' => true,
            'message' => 'Disponibilités mises à jour avec succès',
            'data' => [
                'availability' => $biplaceur->fresh()->availability,
            ],
        ]);
    }

    /**
     * Marquer vol comme fait (biplaceur)
     */
    public function markFlightDone(Request $request, int $id)
    {
        $user = $request->user();
        $biplaceur = $user->biplaceur;

        if (!$biplaceur) {
            return response()->json([
                'success' => false,
                'message' => 'Biplaceur non trouvé',
            ], 404);
        }

        $reservation = $this->biplaceurService->markFlightDone($id, $biplaceur->id);

        return response()->json([
            'success' => true,
            'message' => 'Vol marqué comme effectué',
            'data' => $reservation,
        ]);
    }

    /**
     * Reporter un vol (biplaceur)
     */
    public function rescheduleFlight(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user = $request->user();
        $biplaceur = $user->biplaceur;

        if (!$biplaceur) {
            return response()->json([
                'success' => false,
                'message' => 'Biplaceur non trouvé',
            ], 404);
        }

        $reservation = $this->biplaceurService->rescheduleFlight(
            $id,
            $biplaceur->id,
            $validated['reason']
        );

        return response()->json([
            'success' => true,
            'message' => 'Vol reporté',
            'data' => $reservation,
        ]);
    }

    /**
     * Infos rapides client (biplaceur)
     */
    public function quickInfo(Request $request, int $id)
    {
        $user = $request->user();
        $biplaceur = $user->biplaceur;

        if (!$biplaceur) {
            return response()->json([
                'success' => false,
                'message' => 'Biplaceur non trouvé',
            ], 404);
        }

        $reservation = \App\Models\Reservation::where('id', $id)
            ->where('biplaceur_id', $biplaceur->id)
            ->with(['client', 'options'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'reservation_id' => $reservation->id,
                'client' => [
                    'name' => $reservation->customer_first_name . ' ' . $reservation->customer_last_name,
                    'weight' => $reservation->customer_weight,
                    'phone' => $reservation->customer_phone,
                ],
                'scheduled_at' => $reservation->scheduled_at,
                'options' => $reservation->options->pluck('name'),
                'special_requests' => $reservation->special_requests,
            ],
        ]);
    }
}

