# 🚦 Rate Limiting API

**Date de création:** 2025-11-06  
**Version:** 1.0.0

---

## 📋 Vue d'Ensemble

Le système implémente un **rate limiting par tenant (organisation)** pour protéger l'API contre les abus et garantir la disponibilité pour tous les utilisateurs. Chaque organisation a ses propres limites isolées, ce qui signifie qu'un tenant malveillant ne peut pas affecter les autres.

---

## 🔢 Limites par Type de Route

| Type de Route | Limite | Période | Description |
|---------------|--------|---------|-------------|
| **Authentification** | 30 req/min | 1 minute | Protection contre brute force |
| **Publique** | 60 req/min | 1 minute | Endpoints accessibles sans authentification |
| **Authentifiée** | 120 req/min | 1 minute | Endpoints nécessitant authentification |
| **Admin** | 300 req/min | 1 minute | Endpoints réservés aux administrateurs |

---

## 🔐 Isolation par Tenant

Le rate limiting est **isolé par organisation** (`organization_id`). Cela signifie :

- ✅ Chaque organisation a ses propres compteurs
- ✅ Une organisation qui atteint sa limite n'affecte pas les autres
- ✅ Les limites sont indépendantes par tenant

### Détection de l'Organisation

L'organisation est détectée dans l'ordre suivant :

1. **Header HTTP** : `X-Organization-ID` (priorité)
2. **Session** : `organization_id` dans la session
3. **User authentifié** : Organisation courante de l'utilisateur
4. **Config** : `app.current_organization`
5. **Fallback** : Adresse IP (si aucune organisation détectée)

---

## 📡 Headers de Réponse

Toutes les réponses incluent les headers suivants :

| Header | Description | Exemple |
|--------|-------------|---------|
| `X-RateLimit-Limit` | Limite maximale de requêtes | `60` |
| `X-RateLimit-Remaining` | Nombre de requêtes restantes | `45` |
| `X-RateLimit-Reset` | Timestamp de réinitialisation | `1701936000` |

### Exemple de Réponse Normale

```http
HTTP/1.1 200 OK
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1701936000
```

### Exemple de Réponse Limite Atteinte (429)

```http
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1701936000
Retry-After: 45

{
    "success": false,
    "message": "Too many requests. Please try again later.",
    "retry_after": 45
}
```

---

## 🛣️ Routes et Limites

### Routes Publiques (60 req/min)

- `GET /api/v1/activities`
- `GET /api/v1/instructors`
- `GET /api/v1/sites`
- `POST /api/v1/reservations`
- `GET /api/v1/reservations/{uuid}`
- `POST /api/v1/payments/intent`

### Routes Authentifiées (120 req/min)

- `GET /api/v1/my/reservations`
- `GET /api/v1/notifications`
- `GET /api/v1/instructors/me/sessions`

### Routes Admin (300 req/min)

- `GET /api/v1/admin/dashboard`
- `GET /api/v1/admin/reservations`
- `POST /api/v1/admin/reservations/{id}/capture`
- `GET /api/v1/admin/clients`
- `GET /api/v1/admin/reports`

### Routes Authentification (30 req/min)

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`

---

## 💻 Exemples d'Utilisation

### Requête avec Header Organization

```bash
curl -H "X-Organization-ID: 1" \
     -H "Accept: application/json" \
     https://api.example.com/api/v1/activities
```

### Vérifier les Headers de Rate Limiting

```javascript
fetch('/api/v1/activities', {
    headers: {
        'X-Organization-ID': '1',
        'Accept': 'application/json'
    }
})
.then(response => {
    const limit = response.headers.get('X-RateLimit-Limit');
    const remaining = response.headers.get('X-RateLimit-Remaining');
    const reset = response.headers.get('X-RateLimit-Reset');
    
    console.log(`Limit: ${limit}, Remaining: ${remaining}, Reset: ${reset}`);
});
```

### Gérer la Limite Atteinte

```javascript
async function makeRequest() {
    try {
        const response = await fetch('/api/v1/activities', {
            headers: { 'X-Organization-ID': '1' }
        });
        
        if (response.status === 429) {
            const data = await response.json();
            const retryAfter = data.retry_after || response.headers.get('Retry-After');
            
            console.log(`Rate limit reached. Retry after ${retryAfter} seconds`);
            
            // Attendre avant de réessayer
            await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
            return makeRequest(); // Réessayer
        }
        
        return response.json();
    } catch (error) {
        console.error('Request failed:', error);
    }
}
```

---

## 🔧 Configuration Technique

### Middleware

Le rate limiting est implémenté via le middleware `ThrottlePerTenant` :

```php
// routes/api.php
Route::prefix('reservations')
    ->middleware('throttle.tenant:60,1') // 60 req/min
    ->group(function () {
        // Routes...
    });
```

### Paramètres du Middleware

```php
throttle.tenant:{maxAttempts},{decayMinutes},{keyPrefix}
```

- `maxAttempts` : Nombre maximum de requêtes
- `decayMinutes` : Période de décroissance (en minutes)
- `keyPrefix` : Préfixe de la clé (par défaut: `tenant`)

### Stockage

Le rate limiting utilise **Redis** (ou le driver de cache configuré) pour stocker les compteurs. Les clés sont formatées comme suit :

```
tenant:org:{organization_id}
```

---

## ⚠️ Bonnes Pratiques

### Pour les Développeurs Frontend

1. **Vérifier les headers** : Toujours vérifier `X-RateLimit-Remaining` avant de faire des requêtes en boucle
2. **Gérer 429** : Implémenter une logique de retry avec backoff exponentiel
3. **Cacher les réponses** : Mettre en cache les réponses pour réduire le nombre de requêtes
4. **Batch requests** : Regrouper plusieurs requêtes en une seule quand possible

### Pour les Administrateurs

1. **Surveiller les limites** : Surveiller les logs pour détecter les abus
2. **Ajuster les limites** : Modifier les limites dans `routes/api.php` si nécessaire
3. **Whitelist** : Contacter le support pour whitelist si besoin de limites plus élevées

---

## 🐛 Dépannage

### Problème : Limite atteinte trop rapidement

**Solution** : Vérifier que vous utilisez le bon header `X-Organization-ID` et que vous ne faites pas trop de requêtes simultanées.

### Problème : Headers manquants

**Solution** : Vérifier que le middleware `throttle.tenant` est bien appliqué à la route.

### Problème : Isolation ne fonctionne pas

**Solution** : Vérifier que l'`organization_id` est correctement détecté (vérifier les logs).

---

## 📊 Monitoring

### Métriques à Surveiller

- Nombre de requêtes 429 par organisation
- Temps moyen avant limite atteinte
- Organisations les plus actives
- Patterns d'abus détectés

### Logs

Les dépassements de limite sont loggés avec :
- `organization_id`
- Route appelée
- Timestamp
- IP source

---

## 🔄 Évolution Future

- [ ] Rate limiting adaptatif basé sur l'historique
- [ ] Limites personnalisées par organisation
- [ ] Alertes automatiques pour abus détectés
- [ ] Dashboard de monitoring des limites

---

**Date de création:** 2025-11-06  
**Dernière mise à jour:** 2025-11-06  
**Créé par:** Auto (IA Assistant)

