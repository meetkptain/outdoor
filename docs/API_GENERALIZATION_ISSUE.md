# ⚠️ Incohérence API : Généralisation vs Routes

## 🎯 Problème Identifié

**Phase 2** a généralisé les modèles :
- ✅ `Biplaceur` → `Instructor` (générique)
- ✅ `Flight` → `ActivitySession` (générique)
- ✅ Création du modèle `Activity` (générique)

**MAIS** les routes API utilisent encore les termes spécifiques :
- ❌ `/api/v1/biplaceurs` au lieu de `/api/v1/instructors`
- ❌ `BiplaceurController` au lieu de `InstructorController`
- ❌ Rôle `biplaceur` au lieu de `instructor`
- ❌ `/flights` au lieu de `/sessions` ou `/activity-sessions`

---

## 📊 État Actuel

### Routes API existantes (spécifiques au parapente)

```php
// Routes biplaceurs
GET    /api/v1/biplaceurs
GET    /api/v1/biplaceurs/me/flights
GET    /api/v1/biplaceurs/me/flights/today
GET    /api/v1/biplaceurs/{id}/calendar
POST   /api/v1/biplaceurs/me/flights/{id}/mark-done
// etc.
```

### Modèles généralisés créés

```php
// Modèles génériques (Phase 2)
- Activity (générique)
- ActivitySession (générique)
- Instructor (générique)

// Modules spécifiques (Phase 2)
- App\Modules\Paragliding\Models\Biplaceur (extends Instructor)
- App\Modules\Paragliding\Models\Flight (extends ActivitySession)
- App\Modules\Surfing\Models\SurfingInstructor (extends Instructor)
- App\Modules\Surfing\Models\SurfingSession (extends ActivitySession)
```

---

## 🔧 Solutions Possibles

### Option 1 : Routes Génériques + Alias (Recommandé)

**Avantages** :
- ✅ Cohérence avec l'architecture généralisée
- ✅ Rétrocompatibilité maintenue
- ✅ Support multi-niche natif

**Implémentation** :

```php
// Nouvelles routes génériques
Route::prefix('instructors')->group(function () {
    Route::get('/', [InstructorController::class, 'index']);
    Route::get('/by-activity/{activity_type}', [InstructorController::class, 'byActivity']);
    Route::get('/{id}', [InstructorController::class, 'show']);
    // etc.
});

Route::prefix('activity-sessions')->group(function () {
    Route::get('/', [ActivitySessionController::class, 'index']);
    Route::get('/by-activity/{activity_id}', [ActivitySessionController::class, 'byActivity']);
    // etc.
});

// Alias pour rétrocompatibilité (déprécié)
Route::prefix('biplaceurs')->group(function () {
    Route::get('/', function() {
        return redirect('/api/v1/instructors?activity_type=paragliding');
    });
    // Ou redirection 301 avec header X-Deprecated
});
```

### Option 2 : Routes Génériques uniquement

**Avantages** :
- ✅ Architecture 100% cohérente
- ✅ Pas de duplication

**Inconvénients** :
- ❌ Breaking change pour les clients existants
- ❌ Nécessite migration des apps frontend/mobile

### Option 3 : Garder les routes spécifiques + Routes génériques

**Avantages** :
- ✅ Rétrocompatibilité totale
- ✅ Support multi-niche

**Inconvénients** :
- ⚠️ Duplication de code
- ⚠️ Maintenance plus complexe

---

## ✅ Recommandation : Option 1 (Routes Génériques + Alias)

### Plan d'implémentation

#### Étape 1 : Créer les routes génériques

```php
// ==================== INSTRUCTORS (Générique) ====================
Route::prefix('instructors')->group(function () {
    // Public
    Route::get('/', [InstructorController::class, 'index']);
    Route::get('/by-activity/{activity_type}', [InstructorController::class, 'byActivity']);
    
    // Instructeur authentifié
    Route::middleware(['auth:sanctum', 'role:instructor'])->prefix('me')->group(function () {
        Route::get('/sessions', [InstructorController::class, 'mySessions']);
        Route::get('/sessions/today', [InstructorController::class, 'sessionsToday']);
        Route::get('/calendar', [InstructorController::class, 'calendar']);
        Route::put('/availability', [InstructorController::class, 'updateAvailability']);
        Route::post('/sessions/{id}/mark-done', [InstructorController::class, 'markSessionDone']);
        Route::post('/sessions/{id}/reschedule', [InstructorController::class, 'rescheduleSession']);
    });
    
    // Admin
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/{id}', [InstructorController::class, 'show']);
        Route::get('/{id}/calendar', [InstructorController::class, 'calendar']);
        Route::post('/', [InstructorController::class, 'store']);
        Route::put('/{id}', [InstructorController::class, 'update']);
        Route::delete('/{id}', [InstructorController::class, 'destroy']);
    });
});

// ==================== ACTIVITY SESSIONS (Générique) ====================
Route::prefix('activity-sessions')->group(function () {
    Route::get('/', [ActivitySessionController::class, 'index']);
    Route::get('/by-activity/{activity_id}', [ActivitySessionController::class, 'byActivity']);
    Route::get('/{id}', [ActivitySessionController::class, 'show']);
    // etc.
});
```

#### Étape 2 : Créer les contrôleurs génériques

```php
// app/Http/Controllers/Api/v1/InstructorController.php
class InstructorController extends Controller
{
    public function index(Request $request)
    {
        $activityType = $request->get('activity_type');
        
        $query = Instructor::query();
        
        if ($activityType) {
            $query->whereJsonContains('activity_types', $activityType);
        }
        
        return $query->get();
    }
    
    public function byActivity(string $activityType)
    {
        return Instructor::whereJsonContains('activity_types', $activityType)->get();
    }
    
    // etc.
}
```

#### Étape 3 : Alias pour rétrocompatibilité

```php
// Alias dépréciés (avec header X-Deprecated)
Route::prefix('biplaceurs')->group(function () {
    Route::get('/', function(Request $request) {
        return redirect()->route('api.instructors.index', [
            'activity_type' => 'paragliding'
        ])->header('X-Deprecated', 'true')
          ->header('X-Deprecated-Message', 'Use /api/v1/instructors?activity_type=paragliding');
    });
});
```

#### Étape 4 : Mettre à jour les rôles

```php
// Rôle générique "instructor" au lieu de "biplaceur"
// Garder "biplaceur" comme alias pour rétrocompatibilité
Route::middleware(['auth:sanctum', 'role:instructor,biplaceur'])
```

---

## 📋 Checklist de Migration

### Routes à créer
- [ ] `/api/v1/instructors` (générique)
- [ ] `/api/v1/instructors/by-activity/{type}`
- [ ] `/api/v1/instructors/me/sessions`
- [ ] `/api/v1/activity-sessions` (générique)
- [ ] `/api/v1/activities` (si pas déjà fait)

### Contrôleurs à créer
- [ ] `InstructorController` (générique)
- [ ] `ActivitySessionController` (générique)
- [ ] `ActivityController` (si pas déjà fait)

### Middleware à mettre à jour
- [ ] `RoleMiddleware` : accepter `instructor` ET `biplaceur`
- [ ] Documentation des rôles

### Tests à créer
- [ ] Tests pour routes génériques
- [ ] Tests de rétrocompatibilité (alias)
- [ ] Tests multi-activité (paragliding, surfing)

### Documentation à mettre à jour
- [ ] `docs/API.md` : Routes génériques
- [ ] `docs/API_STATUS.md` : Mettre à jour l'état
- [ ] `docs/ARCHITECTURE_SAAS_MULTI_NICHE.md` : Cohérence
- [ ] OpenAPI/Swagger : Nouvelles routes

---

## 🎯 Conclusion

**Oui, il y a une incohérence !** 

Les modèles ont été généralisés en Phase 2, mais les routes API utilisent encore les termes spécifiques au parapente. Il faut créer des routes génériques pour être cohérent avec l'architecture multi-niche et garder des alias pour la rétrocompatibilité.

**Priorité** : Moyenne (nécessaire pour la cohérence, mais pas bloquant immédiatement)

**Effort estimé** : 1-2 jours pour créer les routes génériques + alias + tests

---

**Prochaine action** : Créer les routes génériques `/instructors` et `/activity-sessions` avec alias pour rétrocompatibilité.

