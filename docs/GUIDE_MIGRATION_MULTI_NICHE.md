# 🚀 Guide de Migration - Généralisation Multi-Niche

**Date:** 2025-11-05  
**Version:** 1.5.0  
**Objectif:** Migrer du système mono-niche (paragliding) vers multi-niche (paragliding, surfing, diving, etc.)

---

## 📋 Table des Matières

1. [Vue d'ensemble](#vue-densemble)
2. [Changements Principaux](#changements-principaux)
3. [Migration de Données](#migration-de-données)
4. [Migration de Code](#migration-de-code)
5. [Migration API](#migration-api)
6. [Tests et Validation](#tests-et-validation)
7. [FAQ](#faq)

---

## 🎯 Vue d'ensemble

### Objectif

Transformer le système de réservation paragliding en un SaaS multi-niche capable de gérer plusieurs activités outdoor (paragliding, surfing, diving, canyoning, etc.).

### Changements Clés

- **Modèles génériques** : `Biplaceur` → `Instructor`, `Flight` → `ActivitySession`
- **API générique** : Routes `/biplaceurs` → `/instructors`, `/flights` → `/activity-sessions`
- **Services génériques** : `BiplaceurService` → `InstructorService`
- **Multi-niche** : Support de plusieurs activités avec contraintes et pricing dynamiques

---

## 🔄 Changements Principaux

### Modèles

#### Avant (Mono-niche)
```php
// Biplaceur (spécifique paragliding)
$biplaceur = Biplaceur::find(1);
$biplaceur->total_flights;

// Flight (spécifique paragliding)
$flight = Flight::where('reservation_id', 1)->first();
```

#### Après (Multi-niche)
```php
// Instructor (générique)
$instructor = Instructor::find(1);
$instructor->activity_types; // ['paragliding', 'surfing']
$instructor->sessions; // ActivitySession

// ActivitySession (générique)
$session = ActivitySession::where('reservation_id', 1)->first();
$session->activity; // Activity (paragliding, surfing, etc.)
```

### Services

#### Avant
```php
use App\Services\BiplaceurService;

$service = new BiplaceurService();
$flights = $service->getFlightsToday($biplaceurId);
```

#### Après
```php
use App\Services\InstructorService;

$service = new InstructorService();
$sessions = $service->getSessionsToday($instructorId);
```

### API Routes

#### Avant
```http
GET /api/v1/biplaceurs
GET /api/v1/biplaceurs/{id}/flights
GET /api/v1/admin/dashboard/flights
GET /api/v1/admin/dashboard/top-biplaceurs
```

#### Après (Nouveau)
```http
GET /api/v1/instructors?activity_type=paragliding
GET /api/v1/instructors/{id}/sessions
GET /api/v1/admin/dashboard/activity-stats
GET /api/v1/admin/dashboard/top-instructors?activity_type=paragliding
```

#### Routes Deprecated (Rétrocompatibilité)
```http
# Ces routes fonctionnent toujours mais redirigent vers les nouvelles
GET /api/v1/biplaceurs → GET /api/v1/instructors?activity_type=paragliding
GET /api/v1/admin/dashboard/flights → GET /api/v1/admin/dashboard/activity-stats
GET /api/v1/admin/dashboard/top-biplaceurs → GET /api/v1/admin/dashboard/top-instructors?activity_type=paragliding
```

---

## 💾 Migration de Données

### Migrations Automatiques

Les migrations suivantes ont été exécutées automatiquement :

1. **`migrate_reservations_flight_type_to_activity.php`**
   - Migre `flight_type` → `activity_type` + `activity_id`
   - Crée des activités paragliding par défaut
   - Stocke `original_flight_type` dans `metadata`

2. **`migrate_reservations_biplaceur_to_instructor.php`**
   - Migre `biplaceur_id` → `instructor_id`
   - Crée des `Instructor` à partir de `Biplaceur`

3. **`migrate_flights_to_activity_sessions.php`**
   - Migre tous les `Flight` → `ActivitySession`
   - Préserve les données dans `metadata`

### Vérification Post-Migration

```bash
# Vérifier que toutes les réservations ont un activity_id
php artisan tinker
>>> Reservation::whereNull('activity_id')->count()
# Doit retourner 0

# Vérifier que tous les biplaceurs ont un instructor_id
>>> Biplaceur::whereNotNull('user_id')->count()
>>> Instructor::where('activity_types', 'like', '%paragliding%')->count()
# Les deux doivent être égaux
```

---

## 💻 Migration de Code

### Contrôleurs

#### Avant
```php
use App\Models\Biplaceur;
use App\Services\BiplaceurService;

public function index()
{
    $biplaceurs = Biplaceur::with('user')->get();
    return response()->json(['data' => $biplaceurs]);
}
```

#### Après
```php
use App\Models\Instructor;
use App\Services\InstructorService;

public function index(Request $request)
{
    $activityType = $request->query('activity_type');
    $instructors = Instructor::with('user')
        ->when($activityType, fn($q) => $q->forActivityType($activityType))
        ->get();
    return response()->json(['data' => $instructors]);
}
```

### Services

#### Avant
```php
class BiplaceurService
{
    public function getFlightsToday(int $biplaceurId): Collection
    {
        return Reservation::where('biplaceur_id', $biplaceurId)
            ->whereDate('scheduled_at', today())
            ->get();
    }
}
```

#### Après
```php
class InstructorService
{
    public function getSessionsToday(int $instructorId): Collection
    {
        return ActivitySession::where('instructor_id', $instructorId)
            ->whereDate('scheduled_at', today())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->with(['activity', 'reservation.client', 'site'])
            ->get();
    }
}
```

### Modèles

#### Avant
```php
class Reservation extends Model
{
    public function biplaceur()
    {
        return $this->belongsTo(Biplaceur::class);
    }
    
    public function flights()
    {
        return $this->hasMany(Flight::class);
    }
}
```

#### Après
```php
class Reservation extends Model
{
    /**
     * @deprecated Utilisez instructor() à la place
     */
    public function biplaceur()
    {
        // Fallback pour rétrocompatibilité
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }
    
    public function instructor()
    {
        return $this->belongsTo(Instructor::class);
    }
    
    public function activitySessions()
    {
        return $this->hasMany(ActivitySession::class);
    }
}
```

---

## 🌐 Migration API

### Endpoints Dépréciés

Tous les endpoints suivants sont **dépréciés** mais continuent de fonctionner pour rétrocompatibilité :

#### Biplaceurs
- `GET /api/v1/biplaceurs` → `GET /api/v1/instructors?activity_type=paragliding`
- `GET /api/v1/biplaceurs/{id}` → `GET /api/v1/instructors/{id}`
- `POST /api/v1/biplaceurs` → `POST /api/v1/instructors` (avec `activity_types: ['paragliding']`)
- `GET /api/v1/biplaceurs/me/flights` → `GET /api/v1/instructors/me/sessions`

#### Dashboard
- `GET /api/v1/admin/dashboard/flights` → `GET /api/v1/admin/dashboard/activity-stats`
- `GET /api/v1/admin/dashboard/top-biplaceurs` → `GET /api/v1/admin/dashboard/top-instructors?activity_type=paragliding`

### Nouveaux Endpoints

#### Instructors (Générique)
```http
# Liste des instructeurs (filtré par activité)
GET /api/v1/instructors?activity_type=paragliding
GET /api/v1/instructors?activity_type=surfing

# Par type d'activité
GET /api/v1/instructors/by-activity/paragliding

# Sessions d'un instructeur
GET /api/v1/instructors/me/sessions
GET /api/v1/instructors/me/sessions/today
```

#### Activities (Nouveau)
```http
# Liste des activités
GET /api/v1/activities

# Par type
GET /api/v1/activities/by-type/paragliding

# Sessions d'une activité
GET /api/v1/activities/{id}/sessions
GET /api/v1/activities/{id}/availability
```

#### Activity Sessions (Nouveau)
```http
# Liste des sessions
GET /api/v1/activity-sessions

# Par activité
GET /api/v1/activity-sessions/by-activity/{activity_id}
```

### Exemples de Requêtes

#### Créer une réservation (Paragliding)
```json
POST /api/v1/reservations
{
    "activity_id": 1,  // ID de l'activité paragliding
    "customer_email": "client@example.com",
    "customer_weight": 75,
    "customer_height": 175,
    "participants_count": 1
}
```

#### Créer une réservation (Surfing)
```json
POST /api/v1/reservations
{
    "activity_id": 2,  // ID de l'activité surfing
    "customer_email": "client@example.com",
    "customer_birth_date": "1990-01-01",
    "participants_count": 2
}
```

---

## 🧪 Tests et Validation

### Tests de Rétrocompatibilité

Les tests suivants vérifient que les anciennes routes fonctionnent toujours :

```php
// Test que /biplaceurs redirige vers /instructors
public function test_biplaceurs_route_redirects_to_instructors()
{
    $response = $this->getJson('/api/v1/biplaceurs');
    $response->assertStatus(200);
    // Vérifie que les données sont des instructeurs avec activity_type=paragliding
}
```

### Tests de Généralisation

```php
// Test création réservation avec activité paragliding
public function test_can_create_paragliding_reservation()
{
    $activity = Activity::factory()->create(['activity_type' => 'paragliding']);
    // ...
}

// Test création réservation avec activité surfing
public function test_can_create_surfing_reservation()
{
    $activity = Activity::factory()->create(['activity_type' => 'surfing']);
    // ...
}
```

### Exécuter les Tests

```bash
# Tous les tests
php artisan test

# Tests spécifiques
php artisan test --filter ReservationControllerGeneralized
php artisan test --filter InstructorServiceTest
```

---

## ❓ FAQ

### Q: Les anciennes routes API fonctionnent-elles encore ?

**R:** Oui, toutes les routes dépréciées fonctionnent encore et redirigent automatiquement vers les nouvelles routes équivalentes. Cependant, il est recommandé de migrer vers les nouvelles routes le plus tôt possible.

### Q: Comment migrer mes données existantes ?

**R:** Les migrations ont été exécutées automatiquement. Si vous avez des données existantes, exécutez :

```bash
php artisan migrate
```

### Q: Puis-je utiliser les anciens modèles (`Biplaceur`, `Flight`) ?

**R:** Les modèles `Biplaceur` et `Flight` sont toujours disponibles pour rétrocompatibilité, mais ils sont marqués comme `@deprecated`. Utilisez `Instructor` et `ActivitySession` pour les nouvelles fonctionnalités.

### Q: Comment ajouter une nouvelle activité (ex: canyoning) ?

**R:** 

1. Créer un module dans `app/Modules/Canyoning/`
2. Créer un fichier `config.php` avec la configuration de l'activité
3. Créer une activité via l'API ou l'admin :

```php
Activity::create([
    'organization_id' => 1,
    'activity_type' => 'canyoning',
    'name' => 'Canyoning',
    'duration_minutes' => 180,
    'pricing_config' => ['base_price' => 80],
    'constraints_config' => ['min_age' => 12, 'swimming_level' => 'required'],
]);
```

### Q: Les tests existants passent-ils toujours ?

**R:** Oui, tous les tests existants ont été mis à jour pour utiliser les nouveaux modèles génériques. Les tests de rétrocompatibilité garantissent que les anciennes fonctionnalités fonctionnent toujours.

### Q: Quand les routes dépréciées seront-elles supprimées ?

**R:** Les routes dépréciées seront supprimées dans une version future (probablement v2.0). Une notification sera envoyée avant la suppression.

---

## 📞 Support

Pour toute question ou problème de migration :

1. Consulter la documentation : `docs/ARCHITECTURE_SAAS_MULTI_NICHE.md`
2. Vérifier les tests : `tests/Feature/*Generalized*`
3. Consulter le plan de correction : `docs/PLAN_CORRECTION_INCOHERENCES.md`

---

**Bon courage avec la migration ! 🚀**

