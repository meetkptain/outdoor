# 🔍 Analyse des Incohérences - Généralisation SaaS Multi-Niche

**Date:** 2025-11-05  
**Objectif:** Identifier toutes les incohérences restantes dans la généralisation vers un SaaS multi-niche

---

## 📊 RÉSUMÉ EXÉCUTIF

**Statut Global:** ⚠️ **Généralisation Partielle** - De nombreuses références spécifiques au paragliding subsistent

**Zones critiques identifiées:**
- ✅ **Modèles génériques:** `Activity`, `ActivitySession`, `Instructor` créés
- ⚠️ **Services:** Nombreux services encore spécifiques au paragliding
- ❌ **Contrôleurs:** Mélange de logique générique et spécifique
- ❌ **Modèle Reservation:** Encore fortement lié au paragliding
- ⚠️ **Routes API:** Routes génériques ajoutées mais routes spécifiques conservées

---

## 🔴 INCOHÉRENCES CRITIQUES

### 1. **Modèle Reservation** - Encore spécifique au paragliding

**Problème:** Le modèle `Reservation` contient encore des champs et relations spécifiques au paragliding.

**Fichier:** `app/Models/Reservation.php`

**Incohérences:**
```php
// ❌ Champs spécifiques au paragliding
'biplaceur_id' => 'nullable|integer',
'tandem_glider_id' => 'nullable|integer',
'flight_type' => 'enum:tandem,biplace,initiation,perfectionnement,autonome',

// ❌ Relations spécifiques
public function biplaceur(): BelongsTo {
    return $this->belongsTo(Biplaceur::class);
}
public function flights(): HasMany {
    return $this->hasMany(Flight::class);
}
public function tandemGlider(): BelongsTo {
    return $this->belongsTo(Resource::class, 'tandem_glider_id');
}

// ✅ Relations génériques (déjà présentes)
public function instructor(): BelongsTo {
    return $this->belongsTo(User::class, 'instructor_id');
}
public function activity(): BelongsTo {
    return $this->belongsTo(Activity::class);
}
```

**Recommandation:**
- Remplacer `biplaceur_id` par `instructor_id` (déjà présent mais doublon)
- Remplacer `flight_type` par `activity_type` (déjà présent dans `activities`)
- Supprimer la relation `flights()` → utiliser `activitySessions()` à la place
- Remplacer `tandem_glider_id` par un champ générique `equipment_id` dans `metadata` ou via `Resource`

---

### 2. **ReservationService** - Logique métier spécifique au paragliding

**Fichier:** `app/Services/ReservationService.php`

**Incohérences identifiées:**

#### 2.1. Validation des contraintes hardcodées (poids/taille)
```php
// ❌ Lignes 38-51: Contraintes spécifiques au paragliding
if ($data['customer_weight'] < 40) {
    throw new \Exception("Poids minimum requis: 40kg");
}
if ($data['customer_weight'] > 120) {
    throw new \Exception("Poids maximum autorisé: 120kg");
}
if ($data['customer_height'] < 140) {
    throw new \Exception("Taille minimum requise: 1.40m (140cm)");
}
```

**Recommandation:** Utiliser les contraintes de l'`Activity`:
```php
$activity = Activity::findOrFail($data['activity_id']);
$constraints = $activity->constraints_config ?? [];

if (isset($constraints['weight'])) {
    if ($data['customer_weight'] < $constraints['weight']['min']) {
        throw new \Exception("Poids minimum requis: {$constraints['weight']['min']}kg");
    }
    if ($data['customer_weight'] > $constraints['weight']['max']) {
        throw new \Exception("Poids maximum autorisé: {$constraints['weight']['max']}kg");
    }
}
```

#### 2.2. Calcul du prix basé sur `flight_type`
```php
// ❌ Ligne 54: Utilise flight_type au lieu de activity
$baseAmount = $this->calculateBaseAmount($data['flight_type'], $data['participants_count']);

// ❌ Lignes 325-336: Prix hardcodés pour paragliding
protected function calculateBaseAmount(string $flightType, int $participants): float
{
    $prices = [
        'tandem' => 120,
        'biplace' => 120,
        'initiation' => 150,
        'perfectionnement' => 180,
        'autonome' => 200,
    ];
    $basePrice = $prices[$flightType] ?? 120;
    return $basePrice * $participants;
}
```

**Recommandation:** Utiliser `Activity->pricing_config`:
```php
$activity = Activity::findOrFail($data['activity_id']);
$pricing = $activity->pricing_config ?? [];
$basePrice = $pricing['base_price'] ?? 120;
return $basePrice * $participants;
```

#### 2.3. Création de `Flight` au lieu de `ActivitySession`
```php
// ❌ Lignes 133-145: Crée des Flight spécifiques
foreach ($data['participants'] ?? [] as $participant) {
    Flight::create([
        'reservation_id' => $reservation->id,
        'participant_first_name' => $participant['first_name'],
        'participant_last_name' => $participant['last_name'],
        // ...
    ]);
}
```

**Recommandation:** Créer des `ActivitySession`:
```php
foreach ($data['participants'] ?? [] as $participant) {
    ActivitySession::create([
        'organization_id' => $reservation->organization_id,
        'activity_id' => $reservation->activity_id,
        'reservation_id' => $reservation->id,
        'scheduled_at' => $reservation->scheduled_at,
        'metadata' => [
            'participant_first_name' => $participant['first_name'],
            'participant_last_name' => $participant['last_name'],
            // ...
        ],
    ]);
}
```

#### 2.4. Logique spécifique aux biplaceurs
```php
// ❌ Lignes 373-449: Validation et assignation de biplaceur
$biplaceur = \App\Models\Biplaceur::find($data['biplaceur_id']);
if ($biplaceur) {
    $flightsToday = $biplaceur->getFlightsToday()->count();
    // ...
}
```

**Recommandation:** Utiliser `Instructor`:
```php
$instructor = Instructor::findOrFail($data['instructor_id']);
$sessionsToday = $instructor->getSessionsToday()->count();
// ...
```

#### 2.5. Stages spécifiques au paragliding
```php
// ❌ Lignes 172, 231: Stages hardcodés
public function addOptions(Reservation $reservation, array $options, string $stage = 'before_flight'): void
{
    // ...
    if ($stage === 'after_flight') {
        // ...
    }
}
```

**Recommandation:** Utiliser les stages de l'`Activity`:
```php
$activity = $reservation->activity;
$workflow = app(ModuleRegistry::class)->get($activity->activity_type)?->getWorkflow();
$stages = $workflow['stages'] ?? ['pending', 'completed'];
```

---

### 3. **ReservationController** - Validation spécifique au paragliding

**Fichier:** `app/Http/Controllers/Api/v1/ReservationController.php`

**Incohérences:**
```php
// ❌ Ligne 32: Validation spécifique au paragliding
'flight_type' => 'required|in:tandem,biplace,initiation,perfectionnement,autonome',

// ❌ Ligne 69: Chargement de flights
'reservation' => $reservation->load(['options', 'flights']),

// ❌ Lignes 90, 117, 208, 238: Chargement de biplaceur
->with(['options', 'flights', 'site', 'instructor', 'payments'])
->with(['biplaceur', 'site', 'options', 'payments'])
```

**Recommandation:**
```php
// ✅ Validation générique
'activity_id' => 'required|exists:activities,id',
'activity_type' => 'required|string',

// ✅ Chargement générique
'reservation' => $reservation->load(['options', 'activitySessions', 'activity', 'instructor'])
```

---

### 4. **ReservationAdminController** - Planification spécifique

**Fichier:** `app/Http/Controllers/Api/v1/Admin/ReservationAdminController.php`

**Incohérences:**
```php
// ❌ Lignes 37-38: Filtre par flight_type
if ($request->has('flight_type')) {
    $query->where('flight_type', $request->flight_type);
}

// ❌ Ligne 106: Validation biplaceur_id
'biplaceur_id' => 'required|exists:biplaceurs,id',

// ❌ Lignes 121, 130: Utilisation de biplaceur_id
'biplaceur_id' => $validated['biplaceur_id'],
'data' => $reservation->fresh()->load(['biplaceur', 'site', 'tandemGlider', 'vehicle']),

// ❌ Ligne 188: Stage spécifique
'stage' => 'nullable|in:before_flight,after_flight',
```

**Recommandation:**
```php
// ✅ Filtre par activity_type
if ($request->has('activity_type')) {
    $query->where('activity_type', $request->activity_type);
}

// ✅ Validation générique
'instructor_id' => 'required|exists:instructors,id',
'activity_id' => 'required|exists:activities,id',

// ✅ Chargement générique
'data' => $reservation->fresh()->load(['activity', 'activitySessions', 'instructor', 'site', 'equipment', 'vehicle'])
```

---

### 5. **Services spécifiques au paragliding**

#### 5.1. **BiplaceurService** - Service entier à supprimer/refactoriser

**Fichier:** `app/Services/BiplaceurService.php`

**Problème:** Service entier dédié aux biplaceurs, devrait être remplacé par `InstructorService` ou intégré dans `Instructor`.

**Recommandation:** 
- Créer `InstructorService` avec les mêmes méthodes
- Supprimer `BiplaceurService`
- Mettre à jour toutes les références

#### 5.2. **StripeTerminalService** - Références à Biplaceur

**Fichier:** `app/Services/StripeTerminalService.php`

**Incohérences:**
```php
// ❌ Lignes 7, 40-45: Utilise Biplaceur
use App\Models\Biplaceur;

public function getConnectionToken(int $biplaceurId): string
{
    $biplaceur = Biplaceur::findOrFail($biplaceurId);
    if (!$biplaceur->can_tap_to_pay) {
        throw new \Exception('Ce biplaceur n\'a pas accès à Stripe Terminal');
    }
    // ...
}
```

**Recommandation:**
```php
use App\Models\Instructor;

public function getConnectionToken(int $instructorId): string
{
    $instructor = Instructor::findOrFail($instructorId);
    $canTapToPay = $instructor->metadata['can_tap_to_pay'] ?? false;
    if (!$canTapToPay) {
        throw new \Exception('Cet instructeur n\'a pas accès à Stripe Terminal');
    }
    $terminalLocationId = $instructor->metadata['stripe_terminal_location_id'] ?? null;
    // ...
}
```

#### 5.3. **VehicleService** - Logique spécifique aux biplaceurs

**Fichier:** `app/Services/VehicleService.php`

**Incohérences:**
```php
// ❌ Lignes 97-105: Compte les biplaceurs comme passagers
// Compter les passagers : clients + biplaceurs
if ($reservation->biplaceur_id) {
    // Ajouter 1 pour le biplaceur si assigné
}

// ❌ Lignes 116-135: Méthode checkWeightLimit avec biplaceurs
public function checkWeightLimit(int $vehicleId, array $passengers, array $biplaceurs = []): bool

// ❌ Lignes 157-177: Calcul poids avec biplaceur
if ($reservation->biplaceur_id) {
    $totalWeight += 80; // Estimation poids biplaceur
}

// ❌ Ligne 194: Sièges nécessaires avec biplaceur
$neededSeats = $reservation->participants_count + ($reservation->biplaceur_id ? 1 : 0);
```

**Recommandation:** Utiliser `instructor_id` et `activitySessions`:
```php
// Compter les passagers : clients + instructeur + participants additionnels
$passengersCount = $reservation->participants_count;
if ($reservation->instructor_id) {
    $passengersCount += 1; // Instructeur
}
$passengersCount += $reservation->activitySessions->count(); // Participants additionnels

// Calcul poids
if ($reservation->instructor_id) {
    $instructorWeight = $reservation->instructor->metadata['weight'] ?? 80;
    $totalWeight += $instructorWeight;
}
```

#### 5.4. **DashboardService** - Statistiques spécifiques

**Fichier:** `app/Services/DashboardService.php`

**Incohérences:**
```php
// ❌ Lignes 32-36: Statistiques de "vols"
'completed_flights' => $reservations->where('status', 'completed')->count(),
'scheduled_flights' => $reservations->where('status', 'scheduled')->count(),
'average_revenue_per_flight' => ...

// ❌ Lignes 97-123: getTopBiplaceurs()
public function getTopBiplaceurs(int $limit = 10, string $period = 'month'): Collection
{
    return Biplaceur::with(['user', 'reservations' => ...])
}

// ❌ Lignes 124-142: getFlightStats()
public function getFlightStats(string $period = 'month'): array
{
    'total_flights' => $reservations->count(),
    'by_flight_type' => $reservations->groupBy('flight_type')->map->count(),
}
```

**Recommandation:**
```php
// ✅ Statistiques génériques
'completed_sessions' => $activitySessions->where('status', 'completed')->count(),
'scheduled_sessions' => $activitySessions->where('status', 'scheduled')->count(),
'average_revenue_per_session' => ...

// ✅ Top instructeurs
public function getTopInstructors(int $limit = 10, string $period = 'month', ?string $activityType = null): Collection
{
    $query = Instructor::with(['user', 'sessions' => ...]);
    if ($activityType) {
        $query->forActivityType($activityType);
    }
    return $query->get();
}

// ✅ Statistiques par activité
public function getActivityStats(string $period = 'month', ?string $activityType = null): array
{
    $query = ActivitySession::whereBetween('scheduled_at', ...);
    if ($activityType) {
        $query->whereHas('activity', fn($q) => $q->where('activity_type', $activityType));
    }
    return [
        'total_sessions' => $query->count(),
        'by_activity_type' => ActivitySession::with('activity')->get()->groupBy('activity.activity_type')->map->count(),
    ];
}
```

---

### 6. **Contrôleurs d'authentification** - Références à biplaceur

**Fichier:** `app/Http/Controllers/Api/v1/AuthController.php`

**Incohérences:**
```php
// ❌ Lignes 121-125: Réponse spécifique biplaceur
if ($user->isBiplaceur() && $user->biplaceur) {
    $response['data']['biplaceur'] = [
        'id' => $user->biplaceur->id,
        'license_number' => $user->biplaceur->license_number,
        'can_tap_to_pay' => $user->biplaceur->can_tap_to_pay,
    ];
}

// ❌ Lignes 178-185: Même problème dans me()
```

**Recommandation:**
```php
// ✅ Réponse générique instructeur
$instructor = $user->getInstructorForOrganization($organization);
if ($instructor) {
    $response['data']['instructor'] = [
        'id' => $instructor->id,
        'activity_types' => $instructor->activity_types,
        'license_number' => $instructor->license_number,
        'metadata' => $instructor->metadata,
    ];
}
```

---

### 7. **PaymentController** - Vérifications spécifiques

**Fichier:** `app/Http/Controllers/Api/v1/PaymentController.php`

**Incohérences:**
```php
// ❌ Lignes 94, 101-104: Vérifications biplaceur
if (!$user->isAdmin() && !$user->isBiplaceur()) {
    // ...
}
if ($user->isBiplaceur()) {
    $biplaceur = $user->biplaceur;
    if ($biplaceur && $payment->reservation->biplaceur_id !== $biplaceur->id) {
        // ...
    }
}

// ❌ Lignes 201-208, 243-253, 296-306: Stripe Terminal pour biplaceurs
if (!$user->isBiplaceur() || !$user->biplaceur) {
    // ...
}
if ($reservation->biplaceur_id !== $user->biplaceur->id) {
    // ...
}
```

**Recommandation:**
```php
// ✅ Vérifications génériques
$instructor = $user->getInstructorForOrganization($organization);
if (!$user->isAdmin() && !$instructor) {
    // ...
}
if ($instructor && $payment->reservation->instructor_id !== $instructor->id) {
    // ...
}
```

---

### 8. **ClientController & ClientService** - Statistiques de vols

**Fichier:** `app/Http/Controllers/Api/v1/ClientController.php`  
**Fichier:** `app/Services/ClientService.php`

**Incohérences:**
```php
// ❌ Lignes 70-72: Statistiques spécifiques
'total_flights' => $client->total_flights,
'last_flight_date' => $client->last_flight_date,

// ❌ Ligne 46 ClientService: Chargement biplaceur
->with(['biplaceur', 'site', 'options', 'payments'])
```

**Recommandation:**
```php
// ✅ Statistiques génériques
'total_sessions' => $client->reservations()->whereHas('activitySessions')->count(),
'last_activity_date' => $client->reservations()->whereHas('activitySessions')->latest('scheduled_at')->value('scheduled_at'),

// ✅ Chargement générique
->with(['activity', 'activitySessions', 'instructor', 'site', 'options', 'payments'])
```

---

### 9. **CouponController** - Types de vol applicables

**Fichier:** `app/Http/Controllers/Api/v1/CouponController.php`

**Incohérences:**
```php
// ❌ Lignes 48, 81: applicable_flight_types
'applicable_flight_types' => 'nullable|array',
```

**Recommandation:**
```php
// ✅ Types d'activités applicables
'applicable_activity_types' => 'nullable|array',
```

---

### 10. **DashboardController** - Routes spécifiques

**Fichier:** `app/Http/Controllers/Api/v1/DashboardController.php`  
**Fichier:** `routes/api.php`

**Incohérences:**
```php
// ❌ Lignes 80-89: flightStats
public function flightStats(Request $request)

// ❌ Lignes 95-104: topBiplaceurs
public function topBiplaceurs(Request $request)

// ❌ routes/api.php ligne 205: Route top-biplaceurs
Route::get('/top-biplaceurs', [DashboardController::class, 'topBiplaceurs']);
```

**Recommandation:**
```php
// ✅ Routes génériques
public function activityStats(Request $request)
public function topInstructors(Request $request)

// ✅ Routes API
Route::get('/top-instructors', [DashboardController::class, 'topInstructors']);
Route::get('/activity-stats', [DashboardController::class, 'activityStats']);
```

---

## ⚠️ INCOHÉRENCES MOYENNES

### 11. **Modèle User** - Méthodes spécifiques

**Fichier:** `app/Models/User.php`

**Incohérences:**
```php
// ❌ Lignes 57-60, 89-92: Relations et méthodes spécifiques
public function biplaceur(): HasOne {
    return $this->hasOne(Biplaceur::class);
}
public function isBiplaceur(): bool {
    return $this->role === 'biplaceur';
}
```

**Recommandation:** Conserver pour rétrocompatibilité mais ajouter méthodes génériques:
```php
public function instructor(): HasOne {
    return $this->hasOne(Instructor::class);
}
public function isInstructor(): bool {
    return $this->getRoleInOrganization($organization) === 'instructor';
}
```

---

### 12. **Routes API** - Routes dupliquées

**Fichier:** `routes/api.php`

**Problème:** Routes génériques (`/instructors`) et routes spécifiques (`/biplaceurs`) coexistent.

**Recommandation:**
- Option A: Supprimer complètement `/biplaceurs` (recommandé si pas de clients en prod)
- Option B: Garder `/biplaceurs` comme alias vers `/instructors` avec filtre `activity_type=paragliding`

---

## 📋 PLAN D'ACTION RECOMMANDÉ

### Phase 1: Refactorisation du modèle Reservation (Priorité: 🔴 CRITIQUE)
1. ✅ Créer migration pour remplacer `biplaceur_id` par `instructor_id` (déjà présent)
2. ❌ Supprimer `flight_type` → utiliser `activity_id` + `Activity->activity_type`
3. ❌ Supprimer relation `flights()` → utiliser `activitySessions()`
4. ❌ Remplacer `tandem_glider_id` par `equipment_id` dans `metadata` ou via `Resource`

### Phase 2: Refactorisation des Services (Priorité: 🔴 CRITIQUE)
1. ❌ Créer `InstructorService` remplaçant `BiplaceurService`
2. ❌ Refactoriser `ReservationService` pour utiliser `Activity` au lieu de `flight_type`
3. ❌ Refactoriser `VehicleService` pour utiliser `instructor_id`
4. ❌ Refactoriser `StripeTerminalService` pour utiliser `Instructor`
5. ❌ Refactoriser `DashboardService` pour statistiques génériques

### Phase 3: Refactorisation des Contrôleurs (Priorité: 🟠 HAUTE)
1. ❌ Refactoriser `ReservationController` pour validation générique
2. ❌ Refactoriser `ReservationAdminController` pour planification générique
3. ❌ Refactoriser `AuthController` pour réponse générique
4. ❌ Refactoriser `PaymentController` pour vérifications génériques
5. ❌ Refactoriser `DashboardController` pour statistiques génériques

### Phase 4: Nettoyage et Routes (Priorité: 🟡 MOYENNE)
1. ❌ Supprimer `BiplaceurController` (ou le garder comme alias)
2. ❌ Supprimer `BiplaceurService`
3. ❌ Mettre à jour toutes les routes pour utiliser versions génériques
4. ❌ Mettre à jour tests pour utiliser modèles génériques

---

## 📊 MÉTRIQUES D'INCOHÉRENCES

| Catégorie | Fichiers affectés | Lignes à modifier | Priorité |
|-----------|-------------------|-------------------|----------|
| **Modèles** | 2 | ~50 | 🔴 Critique |
| **Services** | 5 | ~300 | 🔴 Critique |
| **Contrôleurs** | 7 | ~200 | 🟠 Haute |
| **Routes** | 1 | ~20 | 🟡 Moyenne |
| **Tests** | 10+ | ~150 | 🟡 Moyenne |
| **TOTAL** | **25+** | **~720** | - |

---

## ✅ POINTS POSITIFS

1. ✅ Modèles génériques `Activity`, `ActivitySession`, `Instructor` créés et fonctionnels
2. ✅ Contrôleurs génériques `InstructorController`, `ActivityController`, `ActivitySessionController` créés
3. ✅ Routes génériques ajoutées
4. ✅ Middleware multi-tenant fonctionnel
5. ✅ Système de modules (`ModuleRegistry`) en place

---

## 🎯 CONCLUSION

La généralisation est **partiellement terminée**. Les fondations sont solides (modèles génériques, contrôleurs génériques), mais **la logique métier reste majoritairement spécifique au paragliding**.

**Prochaines étapes prioritaires:**
1. Refactoriser `ReservationService` pour utiliser `Activity`
2. Remplacer toutes les références à `biplaceur` par `instructor`
3. Remplacer `flight_type` par `activity_id` + `Activity`
4. Migrer `Flight` vers `ActivitySession`

Une fois ces étapes terminées, le système sera **vraiment générique** et prêt pour le multi-niche.

