<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Activity;
use App\Models\ActivitySession;
use App\Models\Instructor;
use App\Models\Option;
use App\Models\Coupon;
use App\Models\GiftCard;
use App\Modules\ModuleRegistry;
use App\Modules\ModuleHook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationService
{
    protected PaymentService $paymentService;
    protected NotificationService $notificationService;
    protected VehicleService $vehicleService;
    protected ModuleRegistry $moduleRegistry;

    public function __construct(
        PaymentService $paymentService,
        NotificationService $notificationService,
        VehicleService $vehicleService,
        ModuleRegistry $moduleRegistry
    ) {
        $this->paymentService = $paymentService;
        $this->notificationService = $notificationService;
        $this->vehicleService = $vehicleService;
        $this->moduleRegistry = $moduleRegistry;
    }

    /**
     * Créer une nouvelle réservation
     */
    public function createReservation(array $data): Reservation
    {
        DB::beginTransaction();

        try {
            // Récupérer l'activité
            $activity = Activity::findOrFail($data['activity_id']);
            
            // Récupérer le module correspondant
            $module = $this->moduleRegistry->get($activity->activity_type);
            
            // Hook: Avant création de réservation
            if ($module) {
                $data = $module->beforeReservationCreate($data);
                // Déclencher aussi le hook global si nécessaire
                $this->moduleRegistry->triggerHook(ModuleHook::BEFORE_RESERVATION_CREATE, $activity->activity_type, $data);
            }
            
            // Valider les contraintes depuis l'activité
            $this->validateConstraints($activity, $data);

            // Calculer les montants depuis l'activité
            $baseAmount = $this->calculateBaseAmount($activity, $data['participants_count'], $data['metadata']['original_flight_type'] ?? null);
            $optionsAmount = $this->calculateOptionsAmount($data['options'] ?? [], $data['participants_count']);
            $subtotal = $baseAmount + $optionsAmount;

            // Appliquer coupon si fourni
            $discountAmount = 0;
            $coupon = null;
            if (!empty($data['coupon_code'])) {
                $coupon = Coupon::where('code', $data['coupon_code'])->first();
                if ($coupon && $coupon->isValid()) {
                    // Utiliser activity_type au lieu de flight_type
                    $discountAmount = $coupon->calculateDiscount($subtotal, $activity->activity_type);
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
                'organization_id' => $activity->organization_id,
                'activity_id' => $activity->id,
                'activity_type' => $activity->activity_type,
                'user_id' => $data['user_id'] ?? null,
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_first_name' => $data['customer_first_name'],
                'customer_last_name' => $data['customer_last_name'],
                'customer_birth_date' => $data['customer_birth_date'] ?? null,
                'customer_weight' => $data['customer_weight'] ?? null,
                'customer_height' => $data['customer_height'] ?? null,
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
                'metadata' => [
                    'original_flight_type' => $data['metadata']['original_flight_type'] ?? null,
                ],
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

            // Créer les sessions d'activité pour chaque participant
            // Note: Les sessions seront planifiées plus tard lors de l'assignation
            // Pour l'instant, on peut créer des sessions sans scheduled_at (sera ajouté lors de scheduleReservation)
            // Ou ne pas les créer ici et les créer lors de scheduleReservation
            // Pour l'instant, on ne les crée pas ici car scheduled_at est requis
            // Elles seront créées lors de scheduleReservation ou assignResources

            // Utiliser le bon cadeau si applicable
            if ($giftCard && $giftCardAmount > 0) {
                $giftCard->use($giftCardAmount, $reservation->id);
            }

            // Historique
            $reservation->addHistory('created', null, $reservation->toArray());

            DB::commit();

            // Hook: Après création de réservation
            if ($module) {
                $module->afterReservationCreate($reservation);
                // Déclencher aussi le hook global si nécessaire
                $this->moduleRegistry->triggerHook(ModuleHook::AFTER_RESERVATION_CREATE, $activity->activity_type, $reservation);
            }

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
     * Stages génériques depuis le workflow du module
     */
    public function addOptions(Reservation $reservation, array $options, ?string $stage = null): void
    {
        if (!$reservation->canAddOptions()) {
            throw new \Exception("Impossible d'ajouter des options à cette réservation");
        }

        // Récupérer les stages valides depuis le workflow du module
        $activity = $reservation->activity;
        $module = $this->moduleRegistry->get($activity->activity_type);
        $workflow = $module?->getWorkflow() ?? [];
        $validStages = $workflow['stages'] ?? ['pending', 'authorized', 'scheduled', 'completed'];

        // Si stage non fourni, utiliser le premier stage valide
        if ($stage === null) {
            $stage = 'pending'; // Stage par défaut
        }

        // Valider le stage (pour rétrocompatibilité, accepter aussi 'before_flight' et 'after_flight')
        // Sauvegarder la valeur originale pour la base de données
        $originalStage = $stage;
        $stageMapping = [
            'before_flight' => 'scheduled',
            'after_flight' => 'completed',
        ];
        if (isset($stageMapping[$stage])) {
            $stage = $stageMapping[$stage];
        }

        if (!in_array($stage, $validStages)) {
            throw new \Exception("Stage invalide: {$stage}. Stages valides: " . implode(', ', $validStages));
        }

        // Mapping inverse pour la base de données (qui utilise les anciennes valeurs)
        $dbStageMapping = [
            'scheduled' => 'before_flight',
            'completed' => 'after_flight',
        ];
        // Utiliser la valeur originale si elle existe dans le mapping, sinon utiliser le stage mappé
        $dbStage = $dbStageMapping[$stage] ?? $originalStage ?? $stage;
        // Si le stage original était 'before_flight' ou 'after_flight', l'utiliser directement
        if (in_array($originalStage, ['before_flight', 'after_flight', 'initial'])) {
            $dbStage = $originalStage;
        }

        DB::beginTransaction();

        try {
            $optionsAmount = 0;

            foreach ($options as $optionData) {
                $option = Option::findOrFail($optionData['id']);
                $quantity = $optionData['quantity'] ?? 1;
                $unitPrice = $option->calculatePrice($reservation->participants_count);
                $totalPrice = $unitPrice * $quantity;

                // Vérifier si l'option existe déjà à ce stade (utiliser le stage DB pour la recherche)
                $existing = $reservation->options()
                    ->wherePivot('option_id', $option->id)
                    ->wherePivot('added_at_stage', $dbStage)
                    ->first();

                if ($existing) {
                    // Mettre à jour la quantité
                    $reservation->options()
                        ->updateExistingPivot($option->id, [
                            'quantity' => $quantity,
                            'total_price' => $totalPrice,
                        ], false);
                } else {
                    // Ajouter l'option (utiliser le stage DB pour l'insertion)
                    $reservation->options()->attach($option->id, [
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'added_at_stage' => $dbStage,
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

            // Notifier le client selon le stage (peut être configuré dans le workflow)
            if ($stage === 'completed' || in_array($stage, $workflow['notify_on_stages'] ?? [])) {
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
     * Valider les contraintes depuis l'activité
     */
    protected function validateConstraints(Activity $activity, array $data): void
    {
        $constraints = $activity->constraints_config ?? [];

        // Valider le poids
        if (isset($data['customer_weight']) && isset($constraints['weight'])) {
            $minWeight = $constraints['weight']['min'] ?? null;
            $maxWeight = $constraints['weight']['max'] ?? null;

            if ($minWeight !== null && $data['customer_weight'] < $minWeight) {
                throw new \Exception("Poids minimum requis: {$minWeight}kg");
            }
            if ($maxWeight !== null && $data['customer_weight'] > $maxWeight) {
                throw new \Exception("Poids maximum autorisé: {$maxWeight}kg");
            }
        }

        // Valider la taille
        if (isset($data['customer_height']) && isset($constraints['height'])) {
            $minHeight = $constraints['height']['min'] ?? null;
            $maxHeight = $constraints['height']['max'] ?? null;

            if ($minHeight !== null && $data['customer_height'] < $minHeight) {
                throw new \Exception("Taille minimum requise: {$minHeight}cm");
            }
            if ($maxHeight !== null && $data['customer_height'] > $maxHeight) {
                throw new \Exception("Taille maximum autorisée: {$maxHeight}cm");
            }
        }

        // Valider l'âge si nécessaire
        if (isset($data['customer_birth_date']) && isset($constraints['age'])) {
            $minAge = $constraints['age']['min'] ?? null;
            $maxAge = $constraints['age']['max'] ?? null;

            if ($minAge !== null || $maxAge !== null) {
                $age = \Carbon\Carbon::parse($data['customer_birth_date'])->age;
                if ($minAge !== null && $age < $minAge) {
                    throw new \Exception("Âge minimum requis: {$minAge} ans");
                }
                if ($maxAge !== null && $age > $maxAge) {
                    throw new \Exception("Âge maximum autorisé: {$maxAge} ans");
                }
            }
        }
    }

    /**
     * Calculer le montant de base depuis l'activité
     */
    protected function calculateBaseAmount(Activity $activity, int $participants, ?string $originalFlightType = null): float
    {
        $pricing = $activity->pricing_config ?? [];

        // Si pricing_config contient un prix de base
        if (isset($pricing['base_price'])) {
            return $pricing['base_price'] * $participants;
        }

        // Si pricing_config contient des prix par type (pour rétrocompatibilité avec paragliding)
        if ($originalFlightType && isset($pricing[$originalFlightType])) {
            return $pricing[$originalFlightType] * $participants;
        }

        // Prix par défaut depuis config ou fallback
        $defaultPrice = config('reservations.base_prices.default', 120);
        return $defaultPrice * $participants;
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
     * Planifier une réservation (assigner date et instructeur)
     */
    public function scheduleReservation(Reservation $reservation, array $data): void
    {
        DB::beginTransaction();

        try {
            // Convertir scheduled_at en DateTime si nécessaire
            $scheduledAt = is_string($data['scheduled_at']) 
                ? new \DateTime($data['scheduled_at']) 
                : $data['scheduled_at'];

            // Récupérer l'instructeur (peut venir de instructor_id ou biplaceur_id pour rétrocompatibilité)
            $instructorId = $data['instructor_id'] ?? $data['biplaceur_id'] ?? null;
            if (!$instructorId) {
                throw new \Exception("Aucun instructeur spécifié");
            }

            $instructor = Instructor::findOrFail($instructorId);
            $activity = $reservation->activity;

            // Vérifier que l'instructeur peut enseigner cette activité
            if (!$instructor->canTeachActivity($activity->activity_type)) {
                throw new \Exception("L'instructeur n'est pas qualifié pour cette activité: {$activity->activity_type}");
            }

            // Valider limite de sessions par jour pour l'instructeur
            $sessionsToday = $instructor->getSessionsToday()->count();
            $maxSessions = $instructor->max_sessions_per_day ?? 5;
            
            if ($sessionsToday >= $maxSessions) {
                throw new \Exception("Limite de sessions atteinte pour cet instructeur ({$maxSessions} sessions/jour maximum). Sessions aujourd'hui: {$sessionsToday}");
            }

            // Vérifier pause obligatoire entre rotations (depuis metadata du module ou 30 min par défaut)
            $module = $this->moduleRegistry->get($activity->activity_type);
            $rotationDuration = $module?->getFeature('rotation_duration') ?? 30;

            $lastSession = $instructor->sessions()
                ->whereDate('scheduled_at', $scheduledAt->format('Y-m-d'))
                ->whereIn('status', ['scheduled', 'completed'])
                ->whereHas('reservation', function($q) use ($reservation) {
                    $q->where('id', '!=', $reservation->id);
                })
                ->orderBy('scheduled_at', 'desc')
                ->first();

            if ($lastSession && $lastSession->scheduled_at) {
                $timeDiff = $scheduledAt->getTimestamp() - $lastSession->scheduled_at->getTimestamp();
                $timeDiffMinutes = $timeDiff / 60;
                if ($timeDiff > 0 && $timeDiffMinutes < $rotationDuration) {
                    throw new \Exception("Pause obligatoire de {$rotationDuration} min entre rotations. Dernière session: {$lastSession->scheduled_at->format('H:i')}, Session demandée: {$scheduledAt->format('H:i')} (écart: " . round($timeDiffMinutes) . " min)");
                }
            }

            // Vérifier compétences instructeur pour options requises
            $reservation->load('options');
            foreach ($reservation->options as $option) {
                // Si l'option nécessite une certification (ex: photo, vidéo)
                if (isset($option->metadata['requires_certification'])) {
                    $requiredCert = $option->metadata['requires_certification'];
                    $instructorCerts = $instructor->certifications ?? [];
                    if (!in_array($requiredCert, $instructorCerts)) {
                        throw new \Exception("L'instructeur n'a pas la certification requise pour l'option '{$option->name}': {$requiredCert}");
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

            // Gérer l'équipement via metadata si fourni
            $equipmentId = $data['equipment_id'] ?? $data['tandem_glider_id'] ?? null;
            $metadata = $reservation->metadata ?? [];
            if ($equipmentId) {
                $metadata['equipment_id'] = $equipmentId;
            }

            $oldValues = [
                'scheduled_at' => $reservation->scheduled_at?->toDateTimeString(),
                'instructor_id' => $reservation->instructor_id,
                'site_id' => $reservation->site_id,
            ];

            $reservation->update([
                'scheduled_at' => $scheduledAt,
                'scheduled_time' => $scheduledAt->format('H:i:s'),
                'instructor_id' => $instructorId,
                'site_id' => $data['site_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'metadata' => $metadata,
                'status' => 'scheduled',
            ]);

            // Créer/mettre à jour les ActivitySession avec l'instructeur
            $reservation->load('activitySessions');
            $module = $this->moduleRegistry->get($activity->activity_type);
            
            foreach ($reservation->activitySessions as $session) {
                // Hook: Avant planification de session
                $sessionData = [
                    'instructor_id' => $instructorId,
                    'scheduled_at' => $scheduledAt,
                    'site_id' => $data['site_id'] ?? null,
                ];
                
                if ($module) {
                    $sessionData = $module->beforeSessionSchedule($sessionData);
                    $this->moduleRegistry->triggerHook(ModuleHook::BEFORE_SESSION_SCHEDULE, $activity->activity_type, $sessionData);
                }
                
                $session->update($sessionData);
                
                // Hook: Après planification de session
                if ($module) {
                    $this->moduleRegistry->triggerHook(ModuleHook::AFTER_SESSION_SCHEDULE, $activity->activity_type, $session->fresh());
                }
            }

            $newValues = [
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                'instructor_id' => $instructorId,
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
