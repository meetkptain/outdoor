# 📄 Documentation : Pagination Standardisée

**Date de création** : 2025-11-06  
**Version** : 1.0.0

---

## 🎯 Objectif

Standardiser le format de pagination pour toutes les réponses API, garantissant une expérience cohérente pour les clients de l'API.

---

## 📋 Format Standardisé

### Réponse Paginée

Toutes les réponses paginées suivent ce format :

```json
{
  "success": true,
  "data": [
    // ... éléments de la page
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7,
    "from": 1,
    "to": 15,
    "has_more_pages": true
  }
}
```

### Propriétés de Pagination

| Propriété | Type | Description |
|-----------|------|-------------|
| `current_page` | integer | Numéro de la page actuelle (commence à 1) |
| `per_page` | integer | Nombre d'éléments par page |
| `total` | integer | Nombre total d'éléments (peut être null pour Paginator simple) |
| `last_page` | integer | Numéro de la dernière page (peut être null pour Paginator simple) |
| `from` | integer\|null | Index du premier élément de la page (1-based) |
| `to` | integer\|null | Index du dernier élément de la page (1-based) |
| `has_more_pages` | boolean | Indique s'il y a d'autres pages disponibles |

---

## 🔧 Utilisation

### Dans les Contrôleurs

Le trait `PaginatesApiResponse` fournit plusieurs méthodes utilitaires :

#### 1. `paginatedResponse($paginator, $statusCode = 200)`

Retourne une réponse paginée standardisée depuis un `LengthAwarePaginator` ou `Paginator` Laravel.

```php
use App\Traits\PaginatesApiResponse;

class MyController extends Controller
{
    use PaginatesApiResponse;

    public function index(Request $request)
    {
        $items = Model::paginate(15);
        return $this->paginatedResponse($items);
    }
}
```

#### 2. `paginateQuery($query, $request, $defaultPerPage = 15)`

Crée un paginator depuis une query builder avec gestion automatique des paramètres de requête.

```php
public function index(Request $request)
{
    $query = Model::query();
    // ... filtres ...
    
    $items = $this->paginateQuery($query, $request, 15);
    return $this->paginatedResponse($items);
}
```

#### 3. `paginateCollection($collection, $perPage = null, $page = null)`

Paginer une Collection manuellement (utile pour les données en cache).

```php
public function index(Request $request)
{
    $items = Cache::remember('key', 300, function() {
        return Model::all();
    });
    
    return $this->paginateCollection($items);
}
```

#### 4. `getPaginationParams($request, $defaultPerPage = 15, $maxPerPage = 100)`

Récupère les paramètres de pagination depuis la requête avec validation.

```php
$params = $this->getPaginationParams($request, 15, 100);
// Retourne: ['page' => 1, 'per_page' => 15]
```

---

## 📝 Paramètres de Requête

### Paramètres Standardisés

Tous les endpoints paginés acceptent ces paramètres :

| Paramètre | Type | Par défaut | Description |
|-----------|------|-----------|-------------|
| `page` | integer | 1 | Numéro de la page (minimum: 1) |
| `per_page` | integer | 15 | Nombre d'éléments par page (minimum: 1, maximum: 100) |

### Exemple de Requête

```http
GET /api/v1/reservations?page=2&per_page=20
```

### Validation Automatique

Le trait valide automatiquement les paramètres :
- `page` : minimum 1
- `per_page` : minimum 1, maximum 100 (configurable)

---

## 🎨 Exemples d'Utilisation

### Exemple 1 : Pagination Simple

```php
class ReservationController extends Controller
{
    use PaginatesApiResponse;

    public function index(Request $request)
    {
        $reservations = $this->paginateQuery(
            Reservation::query()->orderBy('created_at', 'desc'),
            $request,
            15
        );

        return $this->paginatedResponse($reservations);
    }
}
```

### Exemple 2 : Pagination avec Filtres

```php
public function index(Request $request)
{
    $query = Reservation::query();

    // Filtres
    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    // Pagination
    $reservations = $this->paginateQuery($query, $request, 15);

    return $this->paginatedResponse($reservations);
}
```

### Exemple 3 : Pagination de Collection (Cache)

```php
public function index(Request $request)
{
    $activities = CacheHelper::remember(
        $organizationId,
        'activities_list',
        300,
        fn() => Activity::where('is_active', true)->get()
    );

    // Paginer la collection
    return $this->paginateCollection($activities);
}
```

---

## 📊 Contrôleurs Utilisant la Pagination

Les contrôleurs suivants utilisent la pagination standardisée :

- ✅ `ReservationController::myReservations()`
- ✅ `ReservationAdminController::index()`
- ✅ `ClientController::index()`
- ✅ `ActivityController::index()` (avec cache)
- ✅ `InstructorController::index()` (avec cache)
- ✅ `ActivitySessionController::index()`
- ✅ `ActivitySessionController::byActivity()`
- ✅ `SiteController::index()`
- ✅ `CouponController::index()`
- ✅ `GiftCardController::index()`

---

## 🔍 Swagger/OpenAPI

Le schéma de pagination est documenté dans Swagger :

- **Schéma** : `Pagination`
- **Schéma** : `PaginatedResponse`

Tous les endpoints paginés incluent automatiquement ces schémas dans leur documentation.

### Exemple d'Annotation Swagger

```php
/**
 * @OA\Get(
 *     path="/api/v1/reservations",
 *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
 *     @OA\Response(
 *         response=200,
 *         description="Liste paginée",
 *         @OA\JsonContent(ref="#/components/schemas/PaginatedResponse")
 *     )
 * )
 */
```

---

## ✅ Bonnes Pratiques

1. **Utiliser le trait** : Toujours utiliser `PaginatesApiResponse` pour les listes
2. **Limite par défaut** : Utiliser 15 éléments par page par défaut
3. **Limite maximale** : Ne pas dépasser 100 éléments par page
4. **Cache** : Utiliser `paginateCollection()` pour les données en cache
5. **Validation** : Laisser le trait gérer la validation des paramètres

---

## 🧪 Tests

Les tests de pagination vérifient :
- ✅ Format de réponse standardisé
- ✅ Paramètres de pagination valides
- ✅ Gestion des limites (min/max)
- ✅ Calcul correct des métadonnées
- ✅ Pagination de collections

---

## 📚 Références

- [Laravel Pagination Documentation](https://laravel.com/docs/pagination)
- [OpenAPI Specification](https://swagger.io/specification/)
- `app/Traits/PaginatesApiResponse.php` - Implémentation du trait

---

**Date de mise à jour** : 2025-11-06  
**Auteur** : Auto (IA Assistant)

