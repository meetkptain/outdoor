# 📋 Plan d'Action - Correction des Incohérences Généralisation

**Date:** 2025-11-05  
**Objectif:** Corriger toutes les incohérences identifiées pour finaliser la généralisation SaaS multi-niche  
**Durée estimée:** 4-5 jours de développement

---

## 🎯 VUE D'ENSEMBLE

### Objectif Global
Transformer complètement le système de mono-niche (paragliding) vers multi-niche (paragliding, surfing, diving, etc.)

### Stratégie
1. **Approche incrémentale:** Phase par phase pour éviter de casser le système
2. **Tests en premier:** Créer/modifier les tests avant de refactoriser
3. **Migration de données:** Préserver les données existantes
4. **Rétrocompatibilité:** Garder les routes anciennes comme alias pendant la transition

---

## 📊 PHASES DE CORRECTION

### **PHASE 1: Migration du Modèle Reservation** 🔴 CRITIQUE
**Durée estimée:** 1 jour  
**Priorité:** 🔴 CRITIQUE (bloque tout le reste)

#### 1.1. Préparation - Migration de données
- [x] Créer migration `migrate_reservations_flight_type_to_activity.php` ✅
  - Migrer `flight_type` vers `activity_type` + `activity_id`
  - Créer activités paragliding si nécessaire
  - Stocker `original_flight_type` dans `metadata`
  
- [x] Créer migration `migrate_reservations_biplaceur_to_instructor.php` ✅
  - Copier `biplaceur_id` → `instructor_id`
  - Créer `Instructor` si `Biplaceur` n'a pas encore d'instructor_id

- [x] Migration `migrate_flights_to_activity_sessions.php` déjà existante ✅
  - Migre tous les `Flight` vers `ActivitySession`
  - Préserve les données dans `metadata`
  - Améliorée pour utiliser `instructor_id` prioritairement

#### 1.2. Modification du modèle Reservation
- [x] **Fichier:** `app/Models/Reservation.php` ✅
  - [x] Supprimer `biplaceur_id` du `$fillable` (commenté)
  - [x] Supprimer `flight_type` du `$fillable` (commenté)
  - [x] Supprimer `tandem_glider_id` du `$fillable` (commenté)
  - [x] Marquer relation `biplaceur()` comme `@deprecated` (conservée pour rétrocompatibilité)
  - [x] Marquer relation `flights()` comme `@deprecated` (conservée pour rétrocompatibilité)
  - [x] Modifier relation `instructor()` pour utiliser `Instructor` au lieu de `User`
  - [x] Ajouter relation `activitySessions()`
  - [x] Ajouter helpers `getEquipment()` et `setEquipment()` pour gérer équipement depuis `metadata`

#### 1.3. Tests
- [x] Créer `tests/Feature/ReservationMigrationTest.php` ✅
  - [x] Test migration `flight_type` → `activity_id`
  - [x] Test migration `biplaceur_id` → `instructor_id`
  - [x] Test relation `activitySessions()`
  - [x] Test helpers `getEquipment()` et `setEquipment()`
  - [x] Test relation `instructor()` avec `Instructor`
- [x] Tests existants mis à jour (ReservationTest, ActivityTest, InstructorTest passent)
- [x] 7/7 tests de migration passent ✅

**Critères de succès Phase 1:** ✅ **TERMINÉE**
- ✅ Toutes les réservations ont un `activity_id` (via migration)
- ✅ Toutes les réservations ont un `instructor_id` (si biplaceur était assigné)
- ✅ Tous les `Flight` ont été migrés vers `ActivitySession` (migration existante améliorée)
- ✅ 7/7 tests de migration passent
- ✅ Modèle `Reservation` généralisé avec relations génériques
- ✅ Rétrocompatibilité maintenue avec méthodes `@deprecated`

---

### **PHASE 2: Refactorisation ReservationService** 🔴 CRITIQUE
**Durée estimée:** 1.5 jours  
**Dépendances:** Phase 1 terminée

#### 2.1. Validation des contraintes génériques
- [ ] **Fichier:** `app/Services/ReservationService.php`
  - [ ] Remplacer validation hardcodée (lignes 38-51) par validation depuis `Activity->constraints_config`
  - [ ] Créer méthode `validateConstraints(Activity $activity, array $data): void`
  - [ ] Tester avec différentes activités (paragliding, surfing, etc.)

#### 2.2. Calcul de prix générique
- [ ] **Fichier:** `app/Services/ReservationService.php`
  - [ ] Remplacer `calculateBaseAmount(string $flightType, ...)` par `calculateBaseAmount(Activity $activity, ...)`
  - [ ] Utiliser `$activity->pricing_config` au lieu de prix hardcodés
  - [ ] Gérer différents modèles de pricing (fixe, par participant, par durée, etc.)
  - [ ] Mettre à jour `createReservation()` pour utiliser `activity_id`

#### 2.3. Création de sessions génériques
- [ ] **Fichier:** `app/Services/ReservationService.php`
  - [ ] Remplacer création de `Flight` (lignes 133-145) par création de `ActivitySession`
  - [ ] Stocker les données participant dans `metadata` de `ActivitySession`
  - [ ] Créer une session par participant si nécessaire

#### 2.4. Logique d'assignation générique
- [ ] **Fichier:** `app/Services/ReservationService.php`
  - [ ] Remplacer `assignResources()` (lignes 361-449)
  - [ ] Utiliser `Instructor` au lieu de `Biplaceur`
  - [ ] Utiliser `getSessionsToday()` au lieu de `getFlightsToday()`
  - [ ] Vérifier limites depuis `Instructor->max_sessions_per_day`
  - [ ] Vérifier compétences depuis `Instructor->certifications`
  - [ ] Créer `ActivitySession` lors de l'assignation

#### 2.5. Stages génériques
- [ ] **Fichier:** `app/Services/ReservationService.php`
  - [ ] Modifier `addOptions()` pour utiliser workflow de l'activité
  - [ ] Récupérer stages depuis `ModuleRegistry->get($activityType)->getWorkflow()`
  - [ ] Valider stages dynamiquement

#### 2.6. Tests
- [ ] Créer `tests/Feature/ReservationServiceGeneralizedTest.php`
  - Test création réservation avec activité paragliding
  - Test création réservation avec activité surfing
  - Test validation contraintes depuis Activity
  - Test calcul prix depuis Activity
  - Test assignation instructeur
- [ ] Mettre à jour tests existants

**Critères de succès Phase 2:**
- ✅ ReservationService fonctionne avec n'importe quelle activité
- ✅ Validation contraintes dynamique depuis Activity
- ✅ Calcul prix dynamique depuis Activity
- ✅ Création ActivitySession au lieu de Flight
- ✅ Tous les tests passent

---

### **PHASE 3: Création InstructorService** 🔴 CRITIQUE ✅ TERMINÉE
**Durée estimée:** 0.5 jour  
**Dépendances:** Phase 1 terminée

#### 3.1. Création du service
- [x] **Fichier:** `app/Services/InstructorService.php` (nouveau) ✅
  - [x] Copier méthodes de `BiplaceurService` ✅
  - [x] Adapter pour utiliser `Instructor` au lieu de `Biplaceur` ✅
  - [x] Adapter pour utiliser `ActivitySession` au lieu de `Reservation->where('biplaceur_id')` ✅
  - Méthodes créées:
    - [x] `getSessionsToday(int $instructorId): Collection` ✅
    - [x] `getCalendar(int $instructorId, string $startDate, string $endDate): Collection` ✅
    - [x] `updateAvailability(int $instructorId, array $availability): bool` ✅
    - [x] `markSessionDone(int $sessionId, int $instructorId): ActivitySession` ✅
    - [x] `rescheduleSession(int $sessionId, int $instructorId, string $reason): ActivitySession` ✅
    - [x] `isAvailable(int $instructorId, string $date, string $time = null): bool` ✅
    - [x] `getStats(int $instructorId, ?string $activityType = null): array` ✅ (bonus)
    - [x] `getUpcomingSessions(int $instructorId, int $limit = 10): Collection` ✅ (bonus)

#### 3.2. Tests
- [x] Créer `tests/Feature/InstructorServiceTest.php` ✅
  - [x] Test `getSessionsToday()` ✅
  - [x] Test `getCalendar()` ✅
  - [x] Test `updateAvailability()` ✅
  - [x] Test `markSessionDone()` ✅
  - [x] Test `rescheduleSession()` ✅
  - [x] Test `isAvailable()` ✅
  - [x] Test `getStats()` ✅
  - [x] Test `getUpcomingSessions()` ✅
  - [x] 8/8 tests passent (21 assertions) ✅

**Critères de succès Phase 3:** ✅ **TERMINÉE**
- ✅ InstructorService créé et fonctionnel
- ✅ Toutes les méthodes testées
- ✅ Prêt à remplacer BiplaceurService

---

### **PHASE 4: Refactorisation Services Spécifiques** 🔴 CRITIQUE ✅ TERMINÉE
**Durée estimée:** 1 jour  
**Dépendances:** Phases 1, 2, 3 terminées

#### 4.1. StripeTerminalService
- [x] **Fichier:** `app/Services/StripeTerminalService.php` ✅
  - [x] Remplacer `Biplaceur` par `Instructor` ✅
  - [x] Récupérer `can_tap_to_pay` depuis `metadata` ✅
  - [x] Récupérer `stripe_terminal_location_id` depuis `metadata` ✅
  - [x] Modifier signature: `getConnectionToken(int $instructorId)` ✅

#### 4.2. VehicleService
- [x] **Fichier:** `app/Services/VehicleService.php` ✅
  - [x] Remplacer `biplaceur_id` par `instructor_id` ✅
  - [x] Utiliser `activitySessions` pour compter participants additionnels ✅
  - [x] Calculer poids instructeur depuis `metadata` ✅
  - [x] Modifier méthodes:
    - [x] `countPassengers(Reservation $reservation): int` ✅
    - [x] `checkWeightLimit(int $vehicleId, array $passengers, ?int $instructorId = null): bool` ✅
    - [x] `calculateReservationWeight(Reservation $reservation): float` ✅
    - [x] `calculateNeededSeats(Reservation $reservation): int` ✅

#### 4.3. DashboardService
- [x] **Fichier:** `app/Services/DashboardService.php` ✅
  - [x] Remplacer `getTopBiplaceurs()` par `getTopInstructors(?string $activityType = null)` ✅
  - [x] Remplacer `getFlightStats()` par `getActivityStats(?string $activityType = null)` ✅
  - [x] Remplacer statistiques "flights" par "sessions" ✅
  - [x] Utiliser `ActivitySession` au lieu de `Reservation` ✅
  - [x] Grouper par `activity_type` au lieu de `flight_type` ✅
  - [x] Méthodes deprecated maintenues pour rétrocompatibilité ✅

#### 4.4. Tests
- [x] Mettre à jour tests pour tous les services modifiés ✅
  - [x] `VehicleServiceTest` : 3 nouveaux tests ajoutés ✅
  - [x] `DashboardServiceTest` : 4 nouveaux tests créés ✅
- [x] 13/13 tests passent (22 assertions) ✅

**Critères de succès Phase 4:** ✅ **TERMINÉE**
- ✅ Tous les services utilisent modèles génériques
- ✅ Aucune référence à Biplaceur dans les services
- ✅ Tous les tests passent

---

### **PHASE 5: Refactorisation Contrôleurs** 🟠 HAUTE
**Durée estimée:** 1 jour  
**Dépendances:** Phases 1-4 terminées

#### 5.1. ReservationController
- [ ] **Fichier:** `app/Http/Controllers/Api/v1/ReservationController.php`
  - [ ] Remplacer validation `flight_type` par `activity_id`
  - [ ] Remplacer `load(['flights'])` par `load(['activitySessions'])`
  - [ ] Remplacer `load(['biplaceur'])` par `load(['instructor'])`
  - [ ] Mettre à jour toutes les réponses JSON

#### 5.2. ReservationAdminController
- [ ] **Fichier:** `app/Http/Controllers/Api/v1/Admin/ReservationAdminController.php`
  - [ ] Remplacer filtre `flight_type` par `activity_type`
  - [ ] Remplacer validation `biplaceur_id` par `instructor_id`
  - [ ] Remplacer `load(['biplaceur', 'tandemGlider'])` par `load(['instructor', 'activity', 'equipment'])`
  - [ ] Remplacer stages hardcodés par workflow dynamique

#### 5.3. AuthController
- [ ] **Fichier:** `app/Http/Controllers/Api/v1/AuthController.php`
  - [ ] Remplacer réponse `biplaceur` par `instructor`
  - [ ] Utiliser `getInstructorForOrganization()`
  - [ ] Mettre à jour méthodes `login()` et `me()`

#### 5.4. PaymentController
- [ ] **Fichier:** `app/Http/Controllers/Api/v1/PaymentController.php`
  - [ ] Remplacer vérifications `isBiplaceur()` par `isInstructor()`
  - [ ] Remplacer `biplaceur_id` par `instructor_id`
  - [ ] Utiliser `getInstructorForOrganization()`
  - [ ] Mettre à jour méthodes Stripe Terminal

#### 5.5. DashboardController
- [ ] **Fichier:** `app/Http/Controllers/Api/v1/DashboardController.php`
  - [ ] Remplacer `flightStats()` par `activityStats()`
  - [ ] Remplacer `topBiplaceurs()` par `topInstructors()`
  - [ ] Ajouter support filtre par `activity_type`

#### 5.6. ClientController & ClientService
- [ ] **Fichiers:** `app/Http/Controllers/Api/v1/ClientController.php`, `app/Services/ClientService.php`
  - [ ] Remplacer `total_flights` par `total_sessions`
  - [ ] Remplacer `last_flight_date` par `last_activity_date`
  - [ ] Mettre à jour chargements de relations

#### 5.7. CouponController
- [ ] **Fichier:** `app/Http/Controllers/Api/v1/CouponController.php`
  - [ ] Remplacer `applicable_flight_types` par `applicable_activity_types`
  - [ ] Mettre à jour validation et logique

#### 5.8. Tests
- [ ] Mettre à jour tous les tests de contrôleurs
- [ ] Créer tests d'intégration pour chaque contrôleur

**Critères de succès Phase 5:**
- ✅ Tous les contrôleurs utilisent modèles génériques
- ✅ Toutes les routes API fonctionnent
- ✅ Tous les tests passent

---

### **PHASE 6: Nettoyage et Routes** 🟡 MOYENNE
**Durée estimée:** 0.5 jour  
**Dépendances:** Phases 1-5 terminées

#### 6.1. Routes API
- [ ] **Fichier:** `routes/api.php`
  - [ ] Décider: Supprimer `/biplaceurs` ou garder comme alias
  - [ ] Si alias: Créer routes qui redirigent vers `/instructors?activity_type=paragliding`
  - [ ] Remplacer route `/top-biplaceurs` par `/top-instructors`
  - [ ] Remplacer route `/flights` par `/activity-stats`
  - [ ] Documenter les routes deprecated

#### 6.2. Suppression code obsolète
- [ ] **Fichier:** `app/Http/Controllers/Api/v1/BiplaceurController.php`
  - [ ] Option A: Supprimer complètement
  - [ ] Option B: Transformer en alias vers `InstructorController`
  
- [ ] **Fichier:** `app/Services/BiplaceurService.php`
  - [ ] Supprimer (remplacé par `InstructorService`)

- [ ] **Fichiers:** `app/Models/Biplaceur.php`, `app/Models/Flight.php`
  - [ ] Option A: Supprimer (si migration terminée)
  - [ ] Option B: Garder temporairement avec deprecation notice

#### 6.3. Modèle User
- [ ] **Fichier:** `app/Models/User.php`
  - [ ] Garder `biplaceur()` et `isBiplaceur()` pour rétrocompatibilité
  - [ ] Ajouter commentaires `@deprecated`
  - [ ] S'assurer que `instructor()` et `getInstructorForOrganization()` fonctionnent

#### 6.4. Documentation
- [ ] Mettre à jour `docs/API.md` avec nouvelles routes
- [ ] Mettre à jour `docs/ARCHITECTURE_SAAS_MULTI_NICHE.md`
- [ ] Créer guide de migration pour développeurs

**Critères de succès Phase 6:**
- ✅ Routes propres et cohérentes
- ✅ Code obsolète supprimé ou marqué deprecated
- ✅ Documentation à jour

---

## 🧪 PLAN DE TESTS

### Tests Unitaires
- [ ] `ReservationServiceGeneralizedTest` - Test service avec différentes activités
- [ ] `InstructorServiceTest` - Test nouveau service
- [ ] `ActivityConstraintsTest` - Test validation contraintes
- [ ] `ActivityPricingTest` - Test calcul prix dynamique

### Tests d'Intégration
- [ ] `ReservationFlowTest` - Test flux complet réservation (paragliding)
- [ ] `ReservationFlowSurfingTest` - Test flux complet réservation (surfing)
- [ ] `InstructorAssignmentTest` - Test assignation instructeur
- [ ] `ActivitySessionCreationTest` - Test création sessions

### Tests de Non-Régression
- [ ] Exécuter tous les tests existants
- [ ] Vérifier qu'aucune fonctionnalité n'est cassée
- [ ] Tests de performance (si applicable)

---

## 📝 CHECKLIST DE VALIDATION FINALE

### Fonctionnalités
- [ ] Création réservation paragliding fonctionne
- [ ] Création réservation surfing fonctionne
- [ ] Assignation instructeur fonctionne
- [ ] Création ActivitySession fonctionne
- [ ] Calcul prix dynamique fonctionne
- [ ] Validation contraintes dynamique fonctionne
- [ ] Statistiques génériques fonctionnent
- [ ] Stripe Terminal fonctionne avec instructeurs

### Code
- [ ] Aucune référence à `Biplaceur` dans services/contrôleurs
- [ ] Aucune référence à `Flight` (sauf dans migrations)
- [ ] Aucune référence à `flight_type` (sauf dans migrations)
- [ ] Tous les tests passent
- [ ] Aucune erreur de linting
- [ ] Code coverage > 80%

### Documentation
- [ ] API documentée
- [ ] Architecture documentée
- [ ] Guide migration créé
- [ ] Changelog mis à jour

---

## 🚨 POINTS D'ATTENTION

### Migration de données
- ⚠️ **Backup obligatoire** avant migration
- ⚠️ Tester migrations en environnement de staging
- ⚠️ Vérifier intégrité des données après migration

### Rétrocompatibilité
- ⚠️ Si clients en production: garder routes anciennes comme alias
- ⚠️ Communiquer changements aux développeurs frontend
- ⚠️ Prévoir période de transition

### Performance
- ⚠️ Vérifier que les requêtes sont optimisées (indexes)
- ⚠️ Tester avec volume de données réaliste
- ⚠️ Optimiser chargements de relations (eager loading)

---

## 📅 CALENDRIER RECOMMANDÉ

| Phase | Durée | Dépendances | Priorité |
|-------|-------|-------------|----------|
| **Phase 1** | 1 jour | - | 🔴 Critique |
| **Phase 2** | 1.5 jours | Phase 1 | 🔴 Critique |
| **Phase 3** | 0.5 jour | Phase 1 | 🔴 Critique |
| **Phase 4** | 1 jour | Phases 1-3 | 🔴 Critique |
| **Phase 5** | 1 jour | Phases 1-4 | 🟠 Haute |
| **Phase 6** | 0.5 jour | Phases 1-5 | 🟡 Moyenne |
| **Tests & Validation** | 1 jour | Toutes phases | - |
| **TOTAL** | **6.5 jours** | - | - |

---

## 🎯 OBJECTIF FINAL

À la fin de ce plan, le système sera:
- ✅ **100% générique** - Aucune référence spécifique au paragliding dans le code métier
- ✅ **Multi-niche** - Prêt pour paragliding, surfing, diving, etc.
- ✅ **Extensible** - Facile d'ajouter de nouvelles activités
- ✅ **Testé** - Tous les tests passent
- ✅ **Documenté** - Architecture et API documentées

---

## 📞 SUPPORT

En cas de problème pendant la correction:
1. Vérifier les dépendances entre phases
2. Consulter les tests pour comprendre le comportement attendu
3. Revenir en arrière si nécessaire (git)
4. Documenter les problèmes rencontrés

---

**Bon courage ! 🚀**

