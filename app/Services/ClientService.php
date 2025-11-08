<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use App\Models\Reservation;
use App\Models\GiftCard;
use Illuminate\Support\Collection;

class ClientService
{
    /**
     * Créer un client avec compte utilisateur
     */
    public function createClient(array $data): Client
    {
        // Créer l'utilisateur
        $user = User::create([
            'name' => $data['first_name'] . ' ' . $data['last_name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password'] ?? \Str::random(16)),
            'role' => 'client',
            'phone' => $data['phone'] ?? null,
        ]);

        // Attacher l'utilisateur à l'organisation courante si disponible
        $organization = config('app.current_organization');
        if ($organization) {
            $user->organizations()->attach($organization->id, [
                'role' => 'client',
            ]);

            // Mettre à jour l'organisation courante de l'utilisateur
            $user->load('organizations');
            $user->setCurrentOrganization($organization);
        }

        // Créer le profil client
        $client = Client::create([
            'organization_id' => $organization?->id,
            'user_id' => $user->id,
            'phone' => $data['phone'] ?? null,
            'weight' => $data['weight'] ?? null,
            'height' => $data['height'] ?? null,
            'medical_notes' => $data['medical_notes'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return $client->load('user');
    }

    /**
     * Récupérer l'historique d'un client
     */
    public function getClientHistory(int $clientId): Collection
    {
        return Reservation::where('client_id', $clientId)
            ->with(['instructor', 'activity', 'site', 'options', 'payments'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Appliquer un bon cadeau à une réservation
     */
    public function applyGiftCard(int $reservationId, string $giftCardCode): bool
    {
        $giftCard = GiftCard::where('code', $giftCardCode)
            ->where('is_active', true)
            ->where('balance', '>', 0)
            ->first();

        if (!$giftCard) {
            throw new \Exception('Bon cadeau invalide ou épuisé');
        }

        $reservation = Reservation::findOrFail($reservationId);

        // Calculer le montant à déduire
        $amountToDeduct = min($giftCard->balance, $reservation->total_amount);

        // Mettre à jour la réservation
        $reservation->update([
            'gift_card_id' => $giftCard->id,
            'discount_amount' => $reservation->discount_amount + $amountToDeduct,
            'total_amount' => $reservation->total_amount - $amountToDeduct,
        ]);

        // Déduire du solde du bon cadeau
        $giftCard->decrement('balance', $amountToDeduct);

        return true;
    }

    /**
     * Mettre à jour le profil client
     */
    public function updateClient(int $clientId, array $data): Client
    {
        $client = Client::findOrFail($clientId);

        $client->update([
            'phone' => $data['phone'] ?? $client->phone,
            'weight' => $data['weight'] ?? $client->weight,
            'height' => $data['height'] ?? $client->height,
            'medical_notes' => $data['medical_notes'] ?? $client->medical_notes,
            'notes' => $data['notes'] ?? $client->notes,
        ]);

        // Mettre à jour l'utilisateur si nécessaire
        if (isset($data['email']) || isset($data['name'])) {
            $user = $client->user;
            if (isset($data['email'])) {
                $user->email = $data['email'];
            }
            if (isset($data['name'])) {
                $user->name = $data['name'];
            }
            $user->save();
        }

        return $client->fresh(['user']);
    }
}

                $user->email = $data['email'];
            }
            if (isset($data['name'])) {
                $user->name = $data['name'];
            }
            $user->save();
        }

        return $client->fresh(['user']);
    }
}


