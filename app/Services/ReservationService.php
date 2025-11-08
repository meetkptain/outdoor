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
use Illuminate\Support\Arr;

class ReservationService
{
    protected PaymentService $paymentService;
    protected NotificationService $notificationService;
    protected VehicleService $vehicleService;
    protected ModuleRegistry $moduleRegistry;
    protected InstructorService $instructorService;

    public function __construct(
        PaymentService $paymentService,
        NotificationService $notificationService,
        VehicleService $vehicleService,
        ModuleRegistry $moduleRegistry,
        InstructorService $instructorService
    ) {
        $this->paymentService = $paymentService;
        $this->notificationService = $notificationService;
        $this->vehicleService = $vehicleService;
        $this->moduleRegistry = $moduleRegistry;
        $this->instructorService = $instructorService;
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
            $this->validateConstraints($activity, $data, $module);

            // Calculer les montants depuis l'activité
            $baseAmount = $this->calculateBaseAmount($activity, $data);
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
            $inputMetadata = $data['metadata'] ?? [];
            $reservationMetadata = array_filter([
                'original_flight_type' => $inputMetadata['original_flight_type'] ?? null,
            ]);

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
                'metadata' => array_merge($inputMetadata, $reservationMetadata),
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

            // Créer les sessions d'activité selon la stratégie définie
            $this->createActivitySessions($reservation, $activity, $data, $module);

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
        $validStages = array_unique(array_merge($validStages, ['scheduled', 'completed']));

        // Si stage non fourni, utiliser le premier stage valide
        if ($stage === null) {
            $stage = $validStages[0] ?? 'pending'; // Stage par défaut
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
        ?int $equipmentId = null,
        ?int $vehicleId = null,
        array $context = []
    ): void {
        DB::beginTransaction();

        try {
            $reservation->loadMissing('activity', 'activitySessions');
            $activity = $reservation->activity;
            $instructor = Instructor::findOrFail($instructorId);

            if ($activity && !$instructor->canTeachActivity($activity->activity_type)) {
                throw new \Exception("L'instructeur n'est pas qualifié pour cette activité: {$activity->activity_type}");
            }

            $scheduledDate = $scheduledAt->format('Y-m-d');
            $module = $activity ? $this->moduleRegistry->get($activity->activity_type) : null;

            if ($reservation->activitySessions->isEmpty()) {
                $sessionStrategy = $activity?->metadata['session_strategy']
                    ?? $module?->getFeature('session_strategy')
                    ?? (Arr::get($context, 'participants') ? 'per_participant' : 'per_reservation');
                $plannedSessions = $sessionStrategy === 'per_participant'
                    ? max(1, $reservation->participants_count ?? 1)
                    : 1;
            } else {
                $plannedSessions = $reservation->activitySessions->count();
            }

            // Vérifier limite de sessions par jour
            if ($activity && $instructor->max_sessions_per_day) {
                $sessionsSameDayCount = ActivitySession::where('instructor_id', $instructorId)
                    ->whereDate('scheduled_at', $scheduledDate)
                    ->where('reservation_id', '!=', $reservation->id)
                    ->whereIn('status', ['scheduled', 'confirmed', 'completed'])
                    ->count();

                if ($sessionsSameDayCount + $plannedSessions > $instructor->max_sessions_per_day) {
                    throw new \Exception("Limite de sessions atteinte pour cet instructeur ({$instructor->max_sessions_per_day} sessions par jour).");
                }
            }

            // Vérifier la pause minimale entre sessions
            $rotationDuration = $module?->getFeature('rotation_duration') ?? 30;

            $lastSession = ActivitySession::where('instructor_id', $instructorId)
                ->whereDate('scheduled_at', $scheduledDate)
                ->where('reservation_id', '!=', $reservation->id)
                ->whereIn('status', ['scheduled', 'confirmed', 'completed'])
                ->orderBy('scheduled_at', 'desc')
                ->first();

            if ($lastSession && $lastSession->scheduled_at) {
                $diffMinutes = ($scheduledAt->getTimestamp() - $lastSession->scheduled_at->getTimestamp()) / 60;
                if ($diffMinutes > 0 && $diffMinutes < $rotationDuration) {
                    throw new \Exception("Pause obligatoire de {$rotationDuration} minutes entre les sessions. Dernière session à {$lastSession->scheduled_at->format('H:i')}.");
                }
            }

            // Vérifier certifications pour options
            $reservation->loadMissing('options');
            foreach ($reservation->options as $option) {
                $requiredCert = Arr::get($option->metadata ?? [], 'requires_certification');
                if ($requiredCert && !in_array($requiredCert, $instructor->certifications ?? [], true)) {
                    throw new \Exception("L'instructeur n'a pas la certification requise ({$requiredCert}) pour l'option {$option->name}.");
                }
            }

            // Vérifier la navette si fournie
            if ($vehicleId) {
                $vehicleCheck = $this->vehicleService->canAssignReservationToVehicle(
                    $reservation,
                    $vehicleId,
                    $scheduledAt
                );

                if (!$vehicleCheck['can_assign']) {
                    throw new \Exception("Navette non disponible: " . implode(', ', $vehicleCheck['errors']));
                }
            }

            // Gérer l'équipement dans metadata
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
                'site_id' => $siteId,
                'vehicle_id' => $vehicleId,
                'metadata' => $metadata,
                'status' => 'scheduled',
            ]);

            // S'assurer que des sessions existent
            if ($reservation->activitySessions()->count() === 0 && $activity) {
                $this->createActivitySessions($reservation->fresh(), $activity, [
                    'participants_count' => $reservation->participants_count,
                    'participants' => Arr::get($context, 'participants'),
                    'duration_minutes' => $reservation->metadata['duration_minutes'] ?? null,
                    'metadata' => $reservation->metadata,
                ], $module);
                $reservation->load('activitySessions');
            }

            $sessionsOverrides = Arr::get($context, 'sessions', []);

            foreach ($reservation->activitySessions as $index => $session) {
                $override = $sessionsOverrides[$index] ?? $sessionsOverrides[$session->id] ?? [];
                $sessionScheduledAt = $override['scheduled_at'] ?? $scheduledAt;
                if (is_string($sessionScheduledAt)) {
                    $sessionScheduledAt = new \DateTime($sessionScheduledAt);
                }

                $sessionMetadata = array_merge($session->metadata ?? [], [
                    'equipment_id' => $equipmentId,
                    'site_id' => $siteId,
                ]);

                $sessionData = [
                    'scheduled_at' => $sessionScheduledAt,
                    'instructor_id' => $instructorId,
                    'site_id' => $siteId,
                    'status' => 'scheduled',
                    'metadata' => $sessionMetadata,
                ];

                if ($module) {
                    $sessionData = $module->beforeSessionSchedule($sessionData);
                    $this->moduleRegistry->triggerHook(ModuleHook::BEFORE_SESSION_SCHEDULE, $activity->activity_type, $sessionData);
                }

                if ($sessionData['scheduled_at'] instanceof \DateTimeInterface) {
                    $sessionData['scheduled_at'] = $sessionData['scheduled_at']->format('Y-m-d H:i:s');
                }

                ActivitySession::where('id', $session->id)->update($sessionData);

                if ($module) {
                    $freshSession = $session->fresh();
                    $this->moduleRegistry->triggerHook(ModuleHook::AFTER_SESSION_SCHEDULE, $activity->activity_type, $freshSession);
                }
            }

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

            $reservation->loadMissing('activitySessions', 'activity');
            $module = $reservation->activity
                ? $this->moduleRegistry->get($reservation->activity->activity_type)
                : null;

            foreach ($reservation->activitySessions as $session) {
                if ($session->status !== 'completed') {
                    $session->update(['status' => 'completed']);

                    if ($module) {
                        $module->afterSessionComplete($session->fresh());
                        $this->moduleRegistry->triggerHook(ModuleHook::AFTER_SESSION_COMPLETE, $reservation->activity->activity_type, $session->fresh());
                    }
                }
            }

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
    protected function validateConstraints(Activity $activity, array $data, $module = null): void
    {
        $activityConstraints = $activity->constraints_config ?? [];
        $moduleConstraints = $module ? $module->getConstraints() : [];
        $constraints = array_replace_recursive($moduleConstraints, $activityConstraints);

        $participants = (int) ($data['participants_count'] ?? 1);
        if ($activity->min_participants && $participants < $activity->min_participants) {
            throw new \Exception("Nombre minimum de participants: {$activity->min_participants}");
        }
        if ($activity->max_participants && $participants > $activity->max_participants) {
            throw new \Exception("Nombre maximum de participants: {$activity->max_participants}");
        }

        if (isset($constraints['participants'])) {
            $minParticipants = $constraints['participants']['min'] ?? null;
            $maxParticipants = $constraints['participants']['max'] ?? null;
            $exactParticipants = $constraints['participants']['exact'] ?? null;

            if ($exactParticipants !== null && $participants !== (int) $exactParticipants) {
                throw new \Exception("Cette activité requiert exactement {$exactParticipants} participant(s).");
            }
            if ($minParticipants !== null && $participants < $minParticipants) {
                throw new \Exception("Nombre minimum de participants requis: {$minParticipants}");
            }
            if ($maxParticipants !== null && $participants > $maxParticipants) {
                throw new \Exception("Nombre maximum de participants autorisé: {$maxParticipants}");
            }
        }

        $weightConstraint = $constraints['weight'] ?? [
            'min' => $constraints['min_weight'] ?? null,
            'max' => $constraints['max_weight'] ?? null,
        ];
        if (isset($data['customer_weight'])) {
            $minWeight = $weightConstraint['min'] ?? null;
            $maxWeight = $weightConstraint['max'] ?? null;
            if ($minWeight !== null && $data['customer_weight'] < $minWeight) {
                throw new \Exception("Poids minimum requis: {$minWeight}kg");
            }
            if ($maxWeight !== null && $data['customer_weight'] > $maxWeight) {
                throw new \Exception("Poids maximum autorisé: {$maxWeight}kg");
            }
        }

        $heightConstraint = $constraints['height'] ?? [
            'min' => $constraints['min_height'] ?? null,
            'max' => $constraints['max_height'] ?? null,
        ];
        if (isset($data['customer_height'])) {
            $minHeight = $heightConstraint['min'] ?? null;
            $maxHeight = $heightConstraint['max'] ?? null;
            if ($minHeight !== null && $data['customer_height'] < $minHeight) {
                throw new \Exception("Taille minimum requise: {$minHeight}cm");
            }
            if ($maxHeight !== null && $data['customer_height'] > $maxHeight) {
                throw new \Exception("Taille maximum autorisée: {$maxHeight}cm");
            }
        }

        $ageConstraint = $constraints['age'] ?? [
            'min' => $constraints['min_age'] ?? null,
            'max' => $constraints['max_age'] ?? null,
        ];
        if (isset($data['customer_birth_date']) && ($ageConstraint['min'] ?? $ageConstraint['max'] ?? null) !== null) {
            $age = \Carbon\Carbon::parse($data['customer_birth_date'])->age;
            $minAge = $ageConstraint['min'] ?? null;
            $maxAge = $ageConstraint['max'] ?? null;
            if ($minAge !== null && $age < $minAge) {
                throw new \Exception("Âge minimum requis: {$minAge} ans");
            }
            if ($maxAge !== null && $age > $maxAge) {
                throw new \Exception("Âge maximum autorisé: {$maxAge} ans");
            }
        }

        // Champs requis dynamiques (ex: metadata.swimming_level)
        $requiredFields = $constraints['required_fields'] ?? [];
        foreach ($requiredFields as $field) {
            $value = data_get($data, $field);
            if ($value === null || $value === '') {
                throw new \Exception("Le champ {$field} est requis pour cette activité.");
            }
        }

        $requiredMetadataFields = $constraints['required_metadata'] ?? [];
        $metadata = $data['metadata'] ?? [];
        foreach ($requiredMetadataFields as $metaKey) {
            $value = Arr::get($metadata, $metaKey);
            if ($value === null || $value === '') {
                throw new \Exception("Le champ metadata.{$metaKey} est requis pour cette activité.");
            }
        }

        $enumConstraints = $constraints['enums'] ?? [];
        foreach ($enumConstraints as $field => $allowedValues) {
            $value = data_get($data, $field);
            if ($value !== null && !in_array($value, $allowedValues, true)) {
                $allowed = implode(', ', $allowedValues);
                throw new \Exception("Valeur invalide pour {$field}. Valeurs autorisées: {$allowed}");
            }
        }
    }

    /**
     * Calculer le montant de base depuis l'activité
     */
    protected function calculateBaseAmount(Activity $activity, array $data): float
    {
        $pricing = $activity->pricing_config ?? [];
        $participants = max(1, (int) ($data['participants_count'] ?? 1));
        $metadata = $data['metadata'] ?? [];

        if (empty($pricing)) {
            $defaultPrice = config('reservations.base_prices.default', 120);
            return $defaultPrice * $participants;
        }

        // Appliquer un variant si défini (ex: selon original_flight_type ou difficulty)
        $variantKey = $metadata['pricing_variant']
            ?? $metadata['original_flight_type']
            ?? $data['original_flight_type']
            ?? null;

        if ($variantKey && isset($pricing['variants'][$variantKey]) && is_array($pricing['variants'][$variantKey])) {
            $pricing = array_replace_recursive($pricing, $pricing['variants'][$variantKey]);
        }

        $model = $pricing['model']
            ?? ($pricing['price_per_participant'] ?? null ? 'per_participant' : null)
            ?? ($pricing['tiers'] ?? null ? 'tiered' : null)
            ?? ($pricing['per_duration'] ?? null ? 'per_duration' : null)
            ?? 'fixed';

        switch ($model) {
            case 'per_participant':
                $unitPrice = $pricing['price_per_participant']
                    ?? $pricing['base_price']
                    ?? config('reservations.base_prices.default', 120);
                return $unitPrice * $participants;

            case 'tiered':
                $tiers = $pricing['tiers'] ?? [];
                if (!empty($tiers)) {
                    foreach ($tiers as $tier) {
                        $max = $tier['max'] ?? null;
                        if ($max !== null && $participants <= $max) {
                            if (isset($tier['per_participant'])) {
                                return $tier['per_participant'] * $participants;
                            }
                            if (isset($tier['price'])) {
                                return $tier['price'];
                            }
                        }
                    }
                    $lastTier = end($tiers);
                    if ($lastTier) {
                        if (isset($lastTier['per_participant'])) {
                            return $lastTier['per_participant'] * $participants;
                        }
                        if (isset($lastTier['price'])) {
                            return $lastTier['price'];
                        }
                    }
                }
                break;

            case 'per_duration':
                $durationConfig = $pricing['per_duration'] ?? [];
                $durationMinutes = $data['duration_minutes']
                    ?? $metadata['duration_minutes']
                    ?? $activity->duration_minutes
                    ?? $durationConfig['default_duration'] ?? 60;
                $unit = $durationConfig['unit_minutes'] ?? 30;
                $unitPrice = $durationConfig['unit_price'] ?? 50;
                $minimum = $durationConfig['minimum_price'] ?? null;

                $units = max(1, (int) ceil($durationMinutes / $unit));
                $amount = $units * $unitPrice;
                if ($minimum !== null) {
                    $amount = max($amount, $minimum);
                }

                // Optionnel: multiplier par participants si configuré
                if (($durationConfig['per_participant'] ?? false) === true) {
                    $amount *= $participants;
                }

                return $amount;

            case 'fixed':
            default:
                $amount = $pricing['base_price'] ?? config('reservations.base_prices.default', 120);
                if (($pricing['apply_per_participant'] ?? false) === true) {
                    return $amount * $participants;
                }
                return $amount;
        }

        // Fallback si aucune règle ne correspond
        $defaultPrice = $pricing['base_price'] ?? config('reservations.base_prices.default', 120);
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
     * Créer les sessions d'activité associées à la réservation
     */
    protected function createActivitySessions(Reservation $reservation, Activity $activity, array $data, $module = null): void
    {
        $existingSessions = $reservation->activitySessions()->count();
        if ($existingSessions > 0) {
            return;
        }

        $participantsData = $data['participants'] ?? null;
        $participantsCount = max(1, (int) ($data['participants_count'] ?? ($participantsData ? count($participantsData) : 1)));
        $sessionStrategy = $activity->metadata['session_strategy']
            ?? ($module?->getFeature('session_strategy') ?? null)
            ?? ($participantsData ? 'per_participant' : 'per_reservation');

        $duration = $data['duration_minutes']
            ?? Arr::get($data, 'metadata.duration_minutes')
            ?? $activity->duration_minutes
            ?? $module?->getFeature('session_duration')
            ?? null;

        if ($sessionStrategy === 'per_participant') {
            $participantsList = [];
            if (is_array($participantsData)) {
                $participantsList = $participantsData;
            } else {
                for ($i = 0; $i < $participantsCount; $i++) {
                    $participantsList[] = [
                        'label' => "Participant " . ($i + 1),
                    ];
                }
            }

            foreach ($participantsList as $index => $participant) {
                ActivitySession::create([
                    'organization_id' => $activity->organization_id,
                    'activity_id' => $activity->id,
                    'reservation_id' => $reservation->id,
                    'duration_minutes' => $participant['duration_minutes'] ?? $duration,
                    'status' => 'pending',
                    'metadata' => [
                        'participant' => $participant,
                        'sequence' => $index + 1,
                        'source' => 'reservation_create',
                    ],
                ]);
            }
            return;
        }

        // Stratégie par réservation (une session pour le groupe)
        ActivitySession::create([
            'organization_id' => $activity->organization_id,
            'activity_id' => $activity->id,
            'reservation_id' => $reservation->id,
            'duration_minutes' => $duration,
            'status' => 'pending',
            'metadata' => [
                'participants_count' => $participantsCount,
                'participants' => $participantsData,
                'source' => 'reservation_create',
            ],
        ]);
    }

    /**
     * Planifier une réservation (assigner date et instructeur)
     */
    public function scheduleReservation(Reservation $reservation, array $data): void
    {
        $scheduledAt = is_string($data['scheduled_at'])
            ? new \DateTime($data['scheduled_at'])
            : $data['scheduled_at'];

        if (!$scheduledAt instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('Le format de scheduled_at est invalide');
        }

        $instructorId = $data['instructor_id'] ?? $data['biplaceur_id'] ?? null;
        if (!$instructorId) {
            throw new \Exception("Aucun instructeur spécifié");
        }

        $context = [
            'participants' => $data['participants'] ?? null,
            'sessions' => $data['sessions'] ?? [],
        ];

        $this->assignResources(
            $reservation,
            new \DateTime($scheduledAt->format('Y-m-d H:i:s')),
            $instructorId,
            $data['site_id'] ?? null,
            $data['equipment_id'] ?? $data['tandem_glider_id'] ?? null,
            $data['vehicle_id'] ?? null,
            $context
        );
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
