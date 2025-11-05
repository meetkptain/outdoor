<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Option;
use App\Models\Coupon;
use App\Models\GiftCard;
use App\Models\Flight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationService
{
    protected PaymentService $paymentService;
    protected NotificationService $notificationService;
    protected VehicleService $vehicleService;

    public function __construct(
        PaymentService $paymentService,
        NotificationService $notificationService,
        VehicleService $vehicleService
    ) {
        $this->paymentService = $paymentService;
        $this->notificationService = $notificationService;
        $this->vehicleService = $vehicleService;
    }

    /**
     * Créer une nouvelle réservation
     */
    public function createReservation(array $data): Reservation
    {
        DB::beginTransaction();

        try {
            // Valider les contraintes client (poids et taille)
            if (isset($data['customer_weight'])) {
                if ($data['customer_weight'] < 40) {
                    throw new \Exception("Poids minimum requis: 40kg");
                }
                if ($data['customer_weight'] > 120) {
                    throw new \Exception("Poids maximum autorisé: 120kg");
                }
            }
            
            if (isset($data['customer_height'])) {
                if ($data['customer_height'] < 140) {
                    throw new \Exception("Taille minimum requise: 1.40m (140cm)");
                }
            }

            // Calculer les montants
            $baseAmount = $this->calculateBaseAmount($data['flight_type'], $data['participants_count']);
            $optionsAmount = $this->calculateOptionsAmount($data['options'] ?? [], $data['participants_count']);
            $subtotal = $baseAmount + $optionsAmount;

            // Appliquer coupon si fourni
            $discountAmount = 0;
            $coupon = null;
            if (!empty($data['coupon_code'])) {
                $coupon = Coupon::where('code', $data['coupon_code'])->first();
                if ($coupon && $coupon->isValid()) {
                    $discountAmount = $coupon->calculateDiscount($subtotal, $data['flight_type']);
                    $coupon->incrementUsage();
                }
            }

            // Appliquer bon cadeau si fourni
            $giftCard = null;
            $giftCardAmount = 0;
            if (!empty($data['gift_card_code'])) {
                $giftCard = GiftCard::where('code', $data['gift_card_code'])->first();
                if ($giftCard && $giftCard->isValid()) {
                    $remaining = $subtotal - $discountAmount;
                    $giftCardAmount = min($remaining, $giftCard->remaining_amount);
                }
            }

            $totalAmount = max(0, $subtotal - $discountAmount - $giftCardAmount);

            // Déterminer le montant à prélever (acompte ou empreinte)
            $paymentType = $data['payment_type'] ?? 'deposit';
            $depositPercentage = config('reservations.deposit_percentage', 30);
            $depositAmount = $paymentType === 'deposit' 
                ? ($totalAmount * $depositPercentage / 100)
                : 0;
            $authorizedAmount = $paymentType === 'authorization' || $paymentType === 'both'
                ? $totalAmount
                : 0;

            // Créer la réservation
            $reservation = Reservation::create([
                'user_id' => $data['user_id'] ?? null,
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_first_name' => $data['customer_first_name'],
                'customer_last_name' => $data['customer_last_name'],
                'customer_birth_date' => $data['customer_birth_date'] ?? null,
                'customer_weight' => $data['customer_weight'] ?? null,
                'customer_height' => $data['customer_height'] ?? null,
                'flight_type' => $data['flight_type'],
                'participants_count' => $data['participants_count'],
                'special_requests' => $data['special_requests'] ?? null,
                'status' => 'pending',
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'gift_card_id' => $giftCard?->id,
                'base_amount' => $baseAmount,
                'options_amount' => $optionsAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'deposit_amount' => $depositAmount,
                'authorized_amount' => $authorizedAmount,
                'payment_type' => $paymentType,
                'payment_status' => 'pending',
            ]);

            // Ajouter les options
            if (!empty($data['options'])) {
                foreach ($data['options'] as $optionData) {
                    $option = Option::findOrFail($optionData['id']);
                    $reservation->options()->attach($option->id, [
                        'quantity' => $optionData['quantity'] ?? 1,
                        'unit_price' => $option->price,
                        'total_price' => $option->calculatePrice($data['participants_count']) * ($optionData['quantity'] ?? 1),
                        'added_at_stage' => 'initial',
                        'added_at' => now(),
                    ]);
                }
            }

            // Créer les flights pour chaque participant
            for ($i = 0; $i < $data['participants_count']; $i++) {
                Flight::create([
                    'reservation_id' => $reservation->id,
                    'participant_first_name' => $data['participants'][$i]['first_name'] ?? $data['customer_first_name'],
                    'participant_last_name' => $data['participants'][$i]['last_name'] ?? $data['customer_last_name'],
                    'participant_birth_date' => $data['participants'][$i]['birth_date'] ?? $data['customer_birth_date'] ?? null,
                    'participant_weight' => $data['participants'][$i]['weight'] ?? $data['customer_weight'] ?? null,
                    'status' => 'pending',
                ]);
            }

            // Utiliser le bon cadeau si applicable
            if ($giftCard && $giftCardAmount > 0) {
                $giftCard->use($giftCardAmount, $reservation->id);
            }

            // Historique
            $reservation->addHistory('created', null, $reservation->toArray());

            DB::commit();

            // Dispatch event (notifications gérées par listeners)
            \App\Events\ReservationCreated::dispatch($reservation);

            return $reservation;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reservation creation failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Ajouter des options à une réservation existante
     */
    public function addOptions(Reservation $reservation, array $options, string $stage = 'before_flight'): void
    {
        if (!$reservation->canAddOptions()) {
            throw new \Exception("Impossible d'ajouter des options à cette réservation");
        }

        DB::beginTransaction();

        try {
            $optionsAmount = 0;

            foreach ($options as $optionData) {
                $option = Option::findOrFail($optionData['id']);
                $quantity = $optionData['quantity'] ?? 1;
                $unitPrice = $option->calculatePrice($reservation->participants_count);
                $totalPrice = $unitPrice * $quantity;

                // Vérifier si l'option existe déjà à ce stade
                $existing = $reservation->options()
                    ->wherePivot('option_id', $option->id)
                    ->wherePivot('added_at_stage', $stage)
                    ->first();

                if ($existing) {
                    // Mettre à jour la quantité
                    $reservation->options()
                        ->updateExistingPivot($option->id, [
                            'quantity' => $quantity,
                            'total_price' => $totalPrice,
                        ], false);
                } else {
                    // Ajouter l'option
                    $reservation->options()->attach($option->id, [
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'added_at_stage' => $stage,
                        'added_at' => now(),
                    ]);
                }

                $optionsAmount += $totalPrice;
            }

            // Mettre à jour les montants
            $reservation->options_amount += $optionsAmount;
            $reservation->total_amount += $optionsAmount;
            $reservation->save();

            // Historique
            $reservation->addHistory('options_added', null, [
                'options' => $options,
                'stage' => $stage,
                'additional_amount' => $optionsAmount,
            ]);

            DB::commit();

            // Notifier le client si nécessaire
            if ($stage === 'after_flight') {
                $this->notificationService->sendOptionsAddedNotification($reservation);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Assigner une date et des ressources à une réservation
     */
    public function assignResources(
        Reservation $reservation,
        \DateTime $scheduledAt,
        int $instructorId,
        ?int $siteId = null,
        ?int $tandemGliderId = null,
        ?int $vehicleId = null
    ): void {
        DB::beginTransaction();

        try {
            $oldValues = [
                'scheduled_at' => $reservation->scheduled_at?->toDateTimeString(),
                'instructor_id' => $reservation->instructor_id,
                'site_id' => $reservation->site_id,
            ];

            $reservation->update([
                'scheduled_at' => $scheduledAt,
                'scheduled_time' => $scheduledAt->format('H:i:s'),
                'instructor_id' => $instructorId,
                'site_id' => $siteId,
                'tandem_glider_id' => $tandemGliderId,
                'vehicle_id' => $vehicleId,
                'status' => 'scheduled',
            ]);

            $newValues = [
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                'instructor_id' => $instructorId,
                'site_id' => $siteId,
            ];

            $reservation->addHistory('assigned', $oldValues, $newValues);

            DB::commit();

            // Envoyer notifications
            $this->notificationService->sendAssignmentNotification($reservation);
            $this->notificationService->scheduleReminder($reservation);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Marquer une réservation comme complétée et capturer le paiement
     */
    public function completeReservation(Reservation $reservation): void
    {
        DB::beginTransaction();

        try {
            $reservation->update(['status' => 'completed']);

            // Capturer le paiement
            $payment = $reservation->payments()
                ->where('status', 'requires_capture')
                ->first();

            if ($payment) {
                $amountToCapture = $reservation->amount_to_capture;
                $this->paymentService->capturePayment($payment, $amountToCapture);
            }

            // Historique
            $reservation->addHistory('completed');

            DB::commit();

            // Dispatch event (notifications gérées par listeners)
            \App\Events\ReservationCompleted::dispatch($reservation);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculer le montant de base selon le type de vol
     */
    protected function calculateBaseAmount(string $flightType, int $participants): float
    {
        $prices = config('reservations.base_prices', [
            'tandem' => 120,
            'biplace' => 150,
            'initiation' => 200,
            'perfectionnement' => 180,
            'autonome' => 100,
        ]);

        $basePrice = $prices[$flightType] ?? 120;
        return $basePrice * $participants;
    }

    /**
     * Calculer le montant des options
     */
    protected function calculateOptionsAmount(array $options, int $participants): float
    {
        $total = 0;

        foreach ($options as $optionData) {
            $option = Option::find($optionData['id']);
            if (!$option) {
                continue;
            }

            $quantity = $optionData['quantity'] ?? 1;
            $price = $option->calculatePrice($participants);
            $total += $price * $quantity;
        }

        return $total;
    }

    /**
     * Planifier une réservation (assigner date et biplaceur)
     */
    public function scheduleReservation(Reservation $reservation, array $data): void
    {
        DB::beginTransaction();

        try {
            // Convertir scheduled_at en DateTime si nécessaire
            $scheduledAt = is_string($data['scheduled_at']) 
                ? new \DateTime($data['scheduled_at']) 
                : $data['scheduled_at'];

            // Valider limite de vols par jour pour le biplaceur
            $biplaceur = \App\Models\Biplaceur::find($data['biplaceur_id']);
            if ($biplaceur) {
                $flightsToday = $biplaceur->getFlightsToday()->count();
                $maxFlights = $biplaceur->max_flights_per_day ?? 5;
                
                if ($flightsToday >= $maxFlights) {
                    throw new \Exception("Limite de vols atteinte pour ce biplaceur ({$maxFlights} vols/jour maximum). Vols aujourd'hui: {$flightsToday}");
                }

                // Vérifier pause obligatoire entre rotations (30 min minimum)
                $lastFlight = $biplaceur->reservations()
                    ->whereDate('scheduled_at', $scheduledAt->format('Y-m-d'))
                    ->whereIn('status', ['scheduled', 'confirmed'])
                    ->where('id', '!=', $reservation->id)
                    ->orderBy('scheduled_at', 'desc')
                    ->first();

                if ($lastFlight && $lastFlight->scheduled_at) {
                    $timeDiff = $scheduledAt->diffInMinutes($lastFlight->scheduled_at);
                    if ($timeDiff > 0 && $timeDiff < 30) {
                        throw new \Exception("Pause obligatoire de 30 min entre rotations. Dernier vol: {$lastFlight->scheduled_at->format('H:i')}, Vol demandé: {$scheduledAt->format('H:i')} (écart: {$timeDiff} min)");
                    }
                }

                // Vérifier compétences biplaceur pour options requises
                $reservation->load('options');
                foreach ($reservation->options as $option) {
                    // Si l'option nécessite une certification (ex: photo, vidéo)
                    if (isset($option->metadata['requires_certification'])) {
                        $requiredCert = $option->metadata['requires_certification'];
                        $biplaceurCerts = $biplaceur->certifications ?? [];
                        if (!in_array($requiredCert, $biplaceurCerts)) {
                            throw new \Exception("Biplaceur n'a pas la certification requise pour l'option '{$option->name}': {$requiredCert}");
                        }
                    }
                }
            }

            // Vérifier capacité et poids de la navette si assignée
            if (!empty($data['vehicle_id'])) {
                $vehicleCheck = $this->vehicleService->canAssignReservationToVehicle(
                    $reservation,
                    $data['vehicle_id'],
                    $scheduledAt
                );

                if (!$vehicleCheck['can_assign']) {
                    throw new \Exception("Navette non disponible: " . implode(', ', $vehicleCheck['errors']));
                }
            }

            $oldValues = [
                'scheduled_at' => $reservation->scheduled_at?->toDateTimeString(),
                'biplaceur_id' => $reservation->biplaceur_id,
                'site_id' => $reservation->site_id,
            ];

            $reservation->update([
                'scheduled_at' => $scheduledAt,
                'scheduled_time' => $scheduledAt->format('H:i:s'),
                'biplaceur_id' => $data['biplaceur_id'],
                'site_id' => $data['site_id'] ?? null,
                'tandem_glider_id' => $data['tandem_glider_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'status' => 'scheduled',
            ]);

            // Mettre à jour aussi instructor_id pour compatibilité
            $biplaceur = \App\Models\Biplaceur::find($data['biplaceur_id']);
            if ($biplaceur) {
                $reservation->update(['instructor_id' => $biplaceur->user_id]);
            }

            $newValues = [
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                'biplaceur_id' => $data['biplaceur_id'],
                'site_id' => $data['site_id'] ?? null,
            ];

            $reservation->addHistory('scheduled', $oldValues, $newValues);

            DB::commit();

            // Envoyer notifications
            $this->notificationService->sendAssignmentNotification($reservation);
            $this->notificationService->scheduleReminder($reservation);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reporter une réservation
     */
    public function rescheduleReservation(Reservation $reservation, string $reason): void
    {
        DB::beginTransaction();

        try {
            $oldScheduledAt = $reservation->scheduled_at;

            $reservation->update([
                'status' => 'rescheduled',
                'scheduled_at' => null,
                'scheduled_time' => null,
            ]);

            // Créer un report
            $reservation->reports()->create([
                'reported_by' => auth()->id(),
                'reason' => 'client_request',
                'reason_details' => $reason,
                'original_date' => $oldScheduledAt ?? now(),
                'is_resolved' => false,
            ]);

            $reservation->addHistory('rescheduled', [
                'scheduled_at' => $oldScheduledAt?->toDateTimeString(),
            ], [
                'status' => 'rescheduled',
                'reason' => $reason,
            ]);

            DB::commit();

            // Pour les reports, on peut aussi créer un event si nécessaire
            // Pour l'instant, notification directe (peut être amélioré avec event)
            $this->notificationService->sendRescheduleNotification($reservation);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Annuler une réservation
     */
    public function cancelReservation(Reservation $reservation, string $reason): void
    {
        DB::beginTransaction();

        try {
            $reservation->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
            ]);

            // Rembourser si nécessaire (selon politique)
            $payment = $reservation->payments()
                ->whereIn('status', ['succeeded', 'requires_capture'])
                ->first();

            if ($payment) {
                // Politique : rembourser l'acompte si annulation > 48h avant
                $scheduledAt = $reservation->scheduled_at;
                if ($scheduledAt && now()->diffInHours($scheduledAt) > 48) {
                    // Remboursement complet
                    $this->paymentService->refundPayment($payment, null, $reason);
                } else {
                    // Remboursement partiel ou aucun selon politique
                    // Pour l'instant, on rembourse quand même
                    $this->paymentService->refundPayment($payment, null, $reason);
                }
            }

            $reservation->addHistory('cancelled', null, [
                'status' => 'cancelled',
                'reason' => $reason,
            ]);

            DB::commit();

            // Dispatch event (notifications gérées par listeners)
            \App\Events\ReservationCancelled::dispatch($reservation, $reason);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
