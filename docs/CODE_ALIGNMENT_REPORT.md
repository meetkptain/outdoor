# 🔍 Rapport d'Alignement Code Laravel vs Documentation

## 📊 Vue d'Ensemble

Analyse de l'alignement entre le code Laravel existant et la documentation UX/Workflow/Blueprint.

**Date d'analyse** : 2024
**Version code** : Phase 2-3 (selon IMPLEMENTATION_STATUS.md)

---

## ✅ Éléments Bien Alignés

### 1. **Statuts de Réservation** ✅

**Code** (migration `create_reservations_table.php`) :
```php
'pending', 'authorized', 'scheduled', 'confirmed', 'completed', 
'cancelled', 'rescheduled', 'refunded'
```

**Documentation** : ✅ Aligné (après corrections)

---

### 2. **Paiements** ✅

**Code** :
- ✅ `PaymentService` avec `createPaymentIntent` (capture_method: manual)
- ✅ Support `deposit`, `authorization`, `capture`, `refund`
- ✅ `StripeTerminalService` pour Tap to Pay
- ✅ Support QR code dans migration `payments`

**Documentation** : ✅ Aligné

---

### 3. **Options** ✅

**Code** :
- ✅ Table `options` avec prix dynamique
- ✅ Table pivot `reservation_options` avec `added_at_stage`
- ✅ Méthode `addOptions()` dans `ReservationService`

**Documentation** : ✅ Aligné

---

### 4. **Biplaceurs** ✅

**Code** :
- ✅ Table `biplaceurs` avec `availability` (JSON)
- ✅ Méthode `isAvailableOn()` pour vérifier disponibilités
- ✅ `getFlightsToday()` pour compter vols du jour
- ✅ Support Stripe Terminal (`can_tap_to_pay`, `stripe_terminal_location_id`)

**Documentation** : ✅ Partiellement aligné (voir manquants)

---

### 5. **Signatures Électroniques** ✅

**Code** :
- ✅ Table `signatures` avec hash de vérification
- ✅ Modèle `Signature` avec relation `reservation`
- ✅ Méthode `verifyHash()`

**Documentation** : ⚠️ Mentionné mais pas dans workflow principal

---

## ⚠️ Incohérences Identifiées

### 1. **Statut `assigned` vs `scheduled`** ❌

**Problème** : Incohérence dans le code

**Code** (`ReservationService.php` ligne 247) :
```php
'status' => 'assigned',  // ❌ Utilise 'assigned'
```

**Migration** (`create_reservations_table.php`) :
```php
'scheduled',      // Date assignée (ancien 'assigned')
```

**Impact** : Le code utilise un statut qui n'existe pas dans la migration !

**Correction nécessaire** :
```php
// ReservationService.php ligne 247
'status' => 'scheduled',  // ✅ Utiliser 'scheduled' au lieu de 'assigned'
```

**Scope** (`Reservation.php` ligne 172) :
```php
public function scopeAssigned($query)
{
    return $query->where('status', 'assigned');  // ❌ Devrait être 'scheduled'
}
```

---

### 2. **Champ Taille Client Manquant** ⚠️

**Documentation UX** : Mentionne `customer_height` (1.75m, etc.)

**Code** :
- ✅ `clients.height` existe (en cm)
- ❌ `reservations.customer_height` n'existe PAS

**Migration `reservations`** :
```php
$table->integer('customer_weight')->nullable(); // kg
// ❌ Pas de customer_height
```

**Impact** : Si client réserve sans compte, la taille n'est pas sauvegardée.

**Correction nécessaire** :
```php
// Migration create_reservations_table.php
$table->integer('customer_height')->nullable(); // cm
```

---

### 3. **Validation Contraintes Client** ❌

**Documentation** : Contraintes obligatoires
- Poids min: 40kg
- Poids max: 120kg
- Taille min: 1.40m

**Code** : ❌ Aucune validation dans `ReservationService::createReservation()`

**Correction nécessaire** :
```php
// ReservationService.php - createReservation()
// Ajouter validation avant création
if ($data['customer_weight'] && $data['customer_weight'] < 40) {
    throw new \Exception("Poids minimum requis: 40kg");
}
if ($data['customer_weight'] && $data['customer_weight'] > 120) {
    throw new \Exception("Poids maximum autorisé: 120kg");
}
if ($data['customer_height'] && $data['customer_height'] < 140) {
    throw new \Exception("Taille minimum requise: 1.40m");
}
```

---

### 4. **Gestion Capacité Navette** ❌

**Documentation** : 
- Capacité max: 9 places (8 passagers + 1 chauffeur)
- Calcul automatique places restantes
- Vérification poids total navette

**Code** :
- ✅ `vehicle_id` existe dans `reservations`
- ✅ `resources.specifications` JSON peut contenir capacity
- ❌ **AUCUNE logique de validation de capacité**
- ❌ **AUCUN comptage des passagers dans une navette**
- ❌ **AUCUNE vérification poids total**

**Code manquant** :
```php
// Service à créer : ShuttleService ou VehicleService
class VehicleService
{
    public function checkCapacity(int $vehicleId, \DateTime $dateTime): bool
    {
        // Compter réservations pour cette navette à cette date/heure
        // Vérifier si < capacité max (8 passagers)
    }
    
    public function getAvailableSeats(int $vehicleId, \DateTime $dateTime): int
    {
        // Retourner places disponibles
    }
    
    public function checkWeightLimit(int $vehicleId, array $passengers): bool
    {
        // Calculer poids total passagers + biplaceurs
        // Vérifier si < limite navette
    }
}
```

---

### 5. **Limite Vols Biplaceur par Jour** ⚠️

**Documentation** : Limite 5 vols/jour par défaut

**Code** :
- ✅ `getFlightsToday()` existe pour compter
- ❌ **AUCUNE validation de limite dans `scheduleReservation()`**
- ❌ Pas de champ `max_flights_per_day` dans table `biplaceurs`

**Code manquant** :
```php
// ReservationService.php - scheduleReservation()
// Ajouter validation avant assignation
$biplaceur = Biplaceur::find($data['biplaceur_id']);
$flightsToday = $biplaceur->getFlightsToday()->count();
$maxFlights = $biplaceur->max_flights_per_day ?? 5;

if ($flightsToday >= $maxFlights) {
    throw new \Exception("Limite de vols atteinte pour ce biplaceur");
}
```

**Migration à ajouter** :
```php
// Migration pour ajouter champ
$table->integer('max_flights_per_day')->default(5);
```

---

### 6. **Compétences Biplaceur pour Options** ❌

**Documentation** : Validation compétences (photo, vidéo) avant assignation

**Code** :
- ✅ `biplaceurs.certifications` JSON existe
- ❌ **AUCUNE validation dans `scheduleReservation()`**
- ❌ Pas de logique pour vérifier si biplaceur peut faire photo/vidéo

**Code manquant** :
```php
// ReservationService.php - scheduleReservation()
// Vérifier compétences si options requises
$reservation->load('options');
foreach ($reservation->options as $option) {
    if ($option->requires_certification) {
        $requiredCert = $option->required_certification; // ex: 'photo', 'video'
        $biplaceurCerts = $biplaceur->certifications ?? [];
        if (!in_array($requiredCert, $biplaceurCerts)) {
            throw new \Exception("Biplaceur n'a pas la certification requise: {$requiredCert}");
        }
    }
}
```

---

### 7. **Gestion Pauses Obligatoires** ❌

**Documentation** : Pause 30 min minimum entre rotations

**Code** : ❌ **AUCUNE logique de vérification des pauses**

**Code manquant** :
```php
// ReservationService.php - scheduleReservation()
// Vérifier pause entre rotations
$lastFlight = $biplaceur->reservations()
    ->whereDate('scheduled_at', $data['scheduled_at']->format('Y-m-d'))
    ->where('status', 'scheduled')
    ->orderBy('scheduled_at', 'desc')
    ->first();

if ($lastFlight) {
    $timeDiff = $data['scheduled_at']->diffInMinutes($lastFlight->scheduled_at);
    if ($timeDiff < 30) {
        throw new \Exception("Pause obligatoire de 30 min entre rotations");
    }
}
```

---

### 8. **Calcul Durée Rotation** ⚠️

**Documentation** : Rotation standard 1h30 (avec pause = 2h15)

**Code** : ❌ **AUCUN calcul automatique de durée rotation**

**Code manquant** : Service ou méthode pour calculer durée rotation selon :
- Transport aller
- Préparation
- Durée vol (selon options)
- Récupération
- Transport retour
- Pause obligatoire

---

## 📋 Éléments Manquants dans le Code

### 1. **Validation Contraintes Complète** ❌

**Manque** :
- Validation poids/taille à la réservation
- Validation capacité navette
- Validation limite biplaceur
- Validation compétences
- Validation pauses

**Où** : `ReservationService::createReservation()` et `scheduleReservation()`

---

### 2. **Service de Gestion Navettes** ❌

**Manque** : Service dédié pour :
- Calculer places disponibles
- Vérifier capacité
- Calculer poids total
- Répartir automatiquement

**Recommandation** : Créer `app/Services/VehicleService.php` ou `ShuttleService.php`

---

### 3. **Champs Manquants dans Migrations** ⚠️

**Migration `reservations`** :
- ❌ `customer_height` (cm)

**Migration `biplaceurs`** :
- ❌ `max_flights_per_day` (default: 5)

**Migration `resources` (pour navettes)** :
- ⚠️ `specifications` JSON existe mais pas de structure standardisée
- Recommandation : Ajouter champs dédiés `max_capacity` (default: 9), `max_weight` (kg)

---

### 4. **Form Requests Validation** ⚠️

**Manque** : Validation dans Form Requests

**Existant** :
- ✅ `CreateReservationRequest`
- ✅ `ScheduleReservationRequest`

**À ajouter** :
```php
// CreateReservationRequest.php
'customer_weight' => 'required|integer|min:40|max:120',
'customer_height' => 'required|integer|min:140', // cm
```

```php
// ScheduleReservationRequest.php
'biplaceur_id' => 'required|exists:biplaceurs,id|custom:biplaceur_available',
'vehicle_id' => 'required|exists:resources,id|custom:vehicle_has_capacity',
```

---

## 🎯 Priorités de Correction

### 🔴 Priorité Haute (Blocants)

1. **Corriger statut `assigned` → `scheduled`** (ReservationService ligne 247)
2. **Ajouter `customer_height` dans migration `reservations`**
3. **Ajouter validation contraintes client** (poids, taille)
4. **Ajouter validation limite biplaceur** dans `scheduleReservation()`

### 🟡 Priorité Moyenne (Important)

5. **Créer `VehicleService` pour gestion navettes**
6. **Ajouter validation capacité navette**
7. **Ajouter validation compétences biplaceur**
8. **Ajouter validation pauses obligatoires**

### 🟢 Priorité Basse (Amélioration)

9. **Ajouter champs manquants migrations** (`max_flights_per_day`, etc.)
10. **Améliorer Form Requests** avec validation complète
11. **Créer service calcul durée rotation**

---

## 📊 Score d'Alignement

| Catégorie | Score | Commentaire |
|-----------|-------|-------------|
| **Structure Base** | 90% | Migrations et modèles bien structurés |
| **Statuts** | 70% | Incohérence `assigned` vs `scheduled` |
| **Paiements** | 95% | Très bien aligné |
| **Options** | 90% | Bien implémenté |
| **Biplaceurs** | 75% | Structure OK, manque validations |
| **Navettes** | 40% | Structure OK mais logique manquante |
| **Contraintes** | 30% | Validations manquantes |
| **Validations** | 50% | Form Requests incomplets |

**Score Global** : **68%** ⚠️

---

## ✅ Plan d'Action Recommandé

### Phase 1 - Corrections Critiques (Semaine 1)

1. Corriger `assigned` → `scheduled` dans `ReservationService`
2. Ajouter migration pour `customer_height` dans `reservations`
3. Ajouter validation contraintes client dans `createReservation()`
4. Corriger `scopeAssigned()` → `scopeScheduled()` dans `Reservation`

### Phase 2 - Validations Essentielles (Semaine 2)

5. Ajouter validation limite biplaceur dans `scheduleReservation()`
6. Créer `VehicleService` avec méthodes de capacité
7. Ajouter validation capacité navette dans `scheduleReservation()`
8. Ajouter champ `max_flights_per_day` dans `biplaceurs`

### Phase 3 - Améliorations (Semaine 3)

9. Ajouter validation compétences biplaceur
10. Ajouter validation pauses obligatoires
11. Améliorer Form Requests avec règles complètes
12. Ajouter service calcul durée rotation

---

## 📝 Notes Importantes

1. **Le code est bien structuré** mais manque les validations métier
2. **Les migrations sont cohérentes** sauf quelques champs manquants
3. **Les services existent** mais ne valident pas toutes les contraintes
4. **La logique métier** doit être ajoutée dans les services

---

**Document créé** : Rapport d'alignement Code vs Documentation
**Version** : 1.0.0
**Date** : 2024

