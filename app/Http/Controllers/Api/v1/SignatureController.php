<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Signature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SignatureController extends Controller
{
    /**
     * Upload une signature
     */
    public function store(Request $request, int $reservation_id)
    {
        $validated = $request->validate([
            'signature' => 'required|string', // Base64 encoded signature
        ]);

        $reservation = Reservation::findOrFail($reservation_id);

        // Vérifier que c'est le client propriétaire (si authentifié)
        $user = $request->user();
        if ($user && $user->isClient()) {
            if ($reservation->user_id !== $user->id && $reservation->client_id !== $user->client?->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette réservation ne vous appartient pas',
                ], 403);
            }
        }

        try {
            // Décoder la signature base64
            $signatureData = base64_decode($validated['signature']);
            if ($signatureData === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format de signature invalide',
                ], 400);
            }

            // Créer un hash de la signature
            $signatureHash = hash('sha256', $signatureData);

            // Sauvegarder le fichier
            $filename = 'signatures/' . $reservation_id . '/' . Str::uuid() . '.png';
            Storage::put($filename, $signatureData);

            // Créer l'enregistrement
            $signature = Signature::updateOrCreate(
                ['reservation_id' => $reservation_id],
                [
                    'signature_hash' => $signatureHash,
                    'file_path' => $filename,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'signed_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Signature enregistrée avec succès',
                'data' => [
                    'id' => $signature->id,
                    'signed_at' => $signature->signed_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement de la signature',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

