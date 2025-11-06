# 📚 Documentation API Swagger/OpenAPI

**Date de création:** 2025-11-06  
**Version:** 1.0.0

---

## 🎯 Vue d'Ensemble

La documentation API est générée automatiquement via **Swagger/OpenAPI** et est accessible via une interface web interactive.

### Accès à la Documentation

- **URL:** `/api/documentation`
- **Format:** OpenAPI 3.0
- **Interface:** Swagger UI

---

## 🚀 Accès Rapide

### Développement Local

```
http://localhost:8000/api/documentation
```

### Production

```
https://api.example.com/api/documentation
```

---

## 📖 Utilisation de la Documentation

### 1. Navigation

- **Tags** : Les endpoints sont organisés par tags (Authentication, Reservations, Activities, etc.)
- **Recherche** : Utilisez la barre de recherche pour trouver rapidement un endpoint
- **Try it out** : Cliquez sur "Try it out" pour tester les endpoints directement depuis l'interface

### 2. Authentification dans Swagger

Pour tester les endpoints authentifiés :

1. **Obtenir un token** :
   - Utilisez l'endpoint `/api/v1/auth/login`
   - Copiez le token retourné

2. **Configurer l'authentification** :
   - Cliquez sur le bouton **"Authorize"** (🔒) en haut à droite
   - Entrez votre token dans le format : `Bearer {votre_token}`
   - Cliquez sur **"Authorize"**

3. **Configurer l'organisation** :
   - Dans le champ **"X-Organization-ID"**, entrez l'ID de votre organisation
   - Cliquez sur **"Authorize"**

### 3. Tester un Endpoint

1. Sélectionnez un endpoint (ex: `POST /api/v1/reservations`)
2. Cliquez sur **"Try it out"**
3. Remplissez les paramètres requis
4. Cliquez sur **"Execute"**
5. Consultez la réponse dans la section **"Responses"**

---

## 🔑 Authentification

### Bearer Token (Sanctum)

Tous les endpoints authentifiés nécessitent un token Bearer :

```http
Authorization: Bearer 1|abcdef1234567890...
```

**Comment obtenir un token :**

```bash
curl -X POST https://api.example.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Organization-ID: 1" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'
```

### Header Organisation

Tous les endpoints nécessitent le header `X-Organization-ID` pour l'isolation multi-tenant :

```http
X-Organization-ID: 1
```

---

## 📋 Endpoints Documentés

### Authentication
- `POST /api/v1/auth/register` - Enregistrement
- `POST /api/v1/auth/login` - Connexion
- `GET /api/v1/auth/me` - Profil utilisateur
- `POST /api/v1/auth/logout` - Déconnexion

### Reservations
- `POST /api/v1/reservations` - Créer une réservation
- `GET /api/v1/reservations/{uuid}` - Détails d'une réservation
- `POST /api/v1/reservations/{uuid}/add-options` - Ajouter des options

### Activities
- `GET /api/v1/activities` - Liste des activités
- `GET /api/v1/activities/{id}` - Détails d'une activité

### Instructors
- `GET /api/v1/instructors` - Liste des instructeurs
- `GET /api/v1/instructors/by-activity/{activity_type}` - Filtrer par activité

### Payments
- `POST /api/v1/payments/intent` - Créer un PaymentIntent
- `POST /api/v1/payments/capture` - Capturer un paiement

---

## 🔧 Génération de la Documentation

### Génération Manuelle

```bash
php artisan l5-swagger:generate
```

### Génération Automatique

La documentation est régénérée automatiquement en mode développement si `L5_SWAGGER_GENERATE_ALWAYS=true` dans `.env`.

### Configuration

Fichier de configuration : `config/l5-swagger.php`

**Variables d'environnement importantes :**

```env
L5_SWAGGER_GENERATE_ALWAYS=false  # true en dev, false en prod
L5_SWAGGER_USE_ABSOLUTE_PATH=true
L5_SWAGGER_BASE_PATH=null  # URL de base de l'API
```

---

## 📝 Ajouter des Annotations

### Exemple de Base

```php
/**
 * @OA\Post(
 *     path="/api/v1/endpoint",
 *     summary="Description courte",
 *     description="Description détaillée",
 *     operationId="endpointName",
 *     tags={"TagName"},
 *     security={{"sanctum": {}}, {"organization": {}}},
 *     @OA\RequestBody(...),
 *     @OA\Response(...)
 * )
 */
public function endpoint(Request $request)
{
    // ...
}
```

### Schémas Réutilisables

Les schémas sont définis dans `app/Models/OpenApiSchemas.php` :

- `Reservation`
- `Activity`
- `Instructor`
- `Payment`
- `Error`
- `Success`

**Utilisation :**

```php
@OA\Property(property="data", ref="#/components/schemas/Reservation")
```

---

## 🛠️ Dépannage

### Problème : Documentation non accessible

**Solution** : Vérifier que la route est bien enregistrée :
```bash
php artisan route:list | grep documentation
```

### Problème : Erreurs lors de la génération

**Solution** : Vérifier les annotations OpenAPI :
```bash
php artisan l5-swagger:generate
```

### Problème : Token non accepté

**Solution** : Vérifier le format du token dans Swagger :
- Format attendu : `Bearer {token}`
- Ne pas inclure les guillemets

---

## 📊 Structure de la Documentation

```
/api/documentation
├── Info (titre, version, description)
├── Servers (URLs de l'API)
├── Security Schemes
│   ├── sanctum (Bearer Token)
│   └── organization (X-Organization-ID)
├── Tags
│   ├── Authentication
│   ├── Reservations
│   ├── Activities
│   └── ...
└── Paths
    ├── /api/v1/auth/login
    ├── /api/v1/reservations
    └── ...
```

---

## 🔄 Mise à Jour

### Quand mettre à jour ?

- ✅ Ajout d'un nouvel endpoint
- ✅ Modification des paramètres d'un endpoint
- ✅ Changement de la structure de réponse
- ✅ Ajout/modification d'un schéma

### Processus

1. Ajouter/modifier les annotations OpenAPI dans le contrôleur
2. Exécuter `php artisan l5-swagger:generate`
3. Vérifier la documentation dans `/api/documentation`
4. Tester les endpoints depuis Swagger UI

---

## 📚 Ressources

- **OpenAPI Specification** : https://swagger.io/specification/
- **Swagger UI** : https://swagger.io/tools/swagger-ui/
- **L5-Swagger** : https://github.com/DarkaOnLine/L5-Swagger

---

## ✅ Checklist pour Nouveaux Endpoints

- [ ] Ajouter annotation `@OA\Post/@OA\Get/@OA\Put/@OA\Delete`
- [ ] Définir `summary` et `description`
- [ ] Ajouter `tags` appropriés
- [ ] Définir `security` (sanctum, organization)
- [ ] Documenter `@OA\RequestBody` si POST/PUT
- [ ] Documenter `@OA\Parameter` pour les query/path params
- [ ] Documenter `@OA\Response` pour tous les codes de statut
- [ ] Utiliser des schémas réutilisables quand possible
- [ ] Ajouter des exemples dans les propriétés
- [ ] Régénérer la documentation
- [ ] Tester depuis Swagger UI

---

**Date de création:** 2025-11-06  
**Dernière mise à jour:** 2025-11-06  
**Créé par:** Auto (IA Assistant)

