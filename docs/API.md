# 📚 Documentation API Complète - Système de Réservation Parapente

**Version API** : v1  
**Dernière mise à jour** : 2025-11-05  
**Total d'endpoints** : 96 routes

---

## Base URL

```
Production: https://api.parapente.example.com/api/v1
Development: http://localhost:8000/api/v1
```

---

## Authentification

L'API utilise **Laravel Sanctum** pour l'authentification par tokens Bearer.

### Obtenir un Token

```http
POST /api/v1/auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password"
}
```

**Response** (200) :
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "user@example.com",
            "role": "client"
        },
        "token": "1|abcdef123456..."
    }
}
```

### Utiliser le Token

```http
Authorization: Bearer {token}
```

### Rôles

- **client** : Utilisateur client (réservations, historique)
- **biplaceur** : Pilote tandem (vols, calendrier, paiements)
- **admin** : Administrateur (toutes les opérations)

---

## 🔐 Authentification (4 routes)

### 1. Inscription Client

```http
POST /api/v1/auth/register
Content-Type: application/json
```

**Body** :
```json
{
    "name": "Jean Dupont",
    "email": "jean@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response** (201) :
```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "Jean Dupont",
            "email": "jean@example.com",
            "role": "client"
        },
        "token": "1|token..."
    }
}
```

### 2. Connexion

```http
POST /api/v1/auth/login
```

**Body** : Identique à l'inscription (email + password)

### 3. Déconnexion

```http
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

### 4. Profil Utilisateur

```http
GET /api/v1/auth/me
Authorization: Bearer {token}
```

---

## 📋 Réservations Publiques (6 routes)

### 1. Créer une Réservation

```http
POST /api/v1/reservations
Content-Type: application/json
```

**Body** :
```json
{
    "customer_email": "client@example.com",
    "customer_phone": "+33612345678",
    "customer_first_name": "Jean",
    "customer_last_name": "Dupont",
    "customer_birth_date": "1990-01-15",
    "customer_weight": 75,
    "customer_height": 175,
    "flight_type": "tandem",
    "participants_count": 1,
    "participants": [
        {
            "first_name": "Jean",
            "last_name": "Dupont",
            "birth_date": "1990-01-15",
            "weight": 75,
            "height": 175
        }
    ],
    "options": [
        {
            "id": 1,
            "quantity": 1
        }
    ],
    "coupon_code": "SUMMER2024",
    "gift_card_code": "GIFT123",
    "payment_type": "deposit",
    "payment_method_id": "pm_1234567890",
    "special_requests": "Vol tôt le matin si possible"
}
```

**Contraintes** :
- `customer_weight` : 40-120 kg
- `customer_height` : 140-250 cm (minimum 1.40m)
- `participants.*.weight` : 40-120 kg
- `participants.*.height` : 140-250 cm

**Response** (201) :
```json
{
    "success": true,
    "data": {
        "reservation": {
            "id": 1,
            "uuid": "550e8400-e29b-41d4-a716-446655440000",
            "customer_email": "client@example.com",
            "status": "pending",
            "base_amount": "120.00",
            "total_amount": "120.00",
            "deposit_amount": "36.00",
            "payment_status": "authorized"
        },
        "payment": {
            "status": "requires_capture",
            "client_secret": "pi_xxx_secret_xxx"
        }
    }
}
```

### 2. Récupérer une Réservation

```http
GET /api/v1/reservations/{uuid}
```

**Response** (200) :
```json
{
    "success": true,
    "data": {
        "id": 1,
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "customer_email": "client@example.com",
        "status": "scheduled",
        "scheduled_at": "2024-06-15T14:00:00Z",
        "site": {
            "id": 1,
            "name": "Site de décollage A"
        },
        "instructor": {
            "id": 5,
            "name": "Pierre Martin"
        },
        "options": [...],
        "payments": [...]
    }
}
```

### 3. Ajouter des Options

```http
POST /api/v1/reservations/{uuid}/add-options
Content-Type: application/json
```

**Body** :
```json
{
    "options": [
        {
            "id": 2,
            "quantity": 1
        }
    ],
    "payment_method_id": "pm_1234567890"
}
```

### 4. Appliquer un Coupon

```http
POST /api/v1/reservations/{uuid}/apply-coupon
Content-Type: application/json
```

**Body** :
```json
{
    "coupon_code": "SUMMER2024"
}
```

### 5. Reporter une Réservation

```http
POST /api/v1/reservations/{uuid}/reschedule
Authorization: Bearer {token}
Content-Type: application/json
```

**Body** :
```json
{
    "scheduled_at": "2024-06-20T14:00:00Z",
    "reason": "Conflit d'horaire"
}
```

### 6. Annuler une Réservation

```http
POST /api/v1/reservations/{uuid}/cancel
Authorization: Bearer {token}
Content-Type: application/json
```

**Body** :
```json
{
    "reason": "Raison d'annulation"
}
```

---

## 👤 Réservations Client Authentifié (4 routes)

### 1. Mes Réservations

```http
GET /api/v1/my/reservations
Authorization: Bearer {token}
```

**Query Parameters** :
- `status` : Filtrer par statut
- `page` : Numéro de page
- `per_page` : Résultats par page (défaut: 15)

### 2. Détails d'une Réservation

```http
GET /api/v1/my/reservations/{id}
Authorization: Bearer {token}
```

### 3. Historique d'une Réservation

```http
GET /api/v1/my/reservations/{id}/history
Authorization: Bearer {token}
```

**Response** (200) :
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "action": "status_changed",
            "old_values": {"status": "pending"},
            "new_values": {"status": "scheduled"},
            "created_at": "2024-06-10T10:00:00Z",
            "user": {
                "id": 2,
                "name": "Admin"
            }
        }
    ]
}
```

### 4. Ajouter des Options (Client)

```http
POST /api/v1/my/reservations/{id}/add-options
Authorization: Bearer {token}
Content-Type: application/json
```

---

## 💳 Paiements (7 routes)

### 1. Créer PaymentIntent

```http
POST /api/v1/payments/intent
Content-Type: application/json
```

**Body** :
```json
{
    "reservation_id": 1,
    "amount": 120.00,
    "currency": "EUR",
    "payment_method_id": "pm_xxx",
    "capture_method": "manual"
}
```

### 2. Capturer un Paiement

```http
POST /api/v1/payments/capture
Authorization: Bearer {token} (admin ou biplaceur)
Content-Type: application/json
```

**Body** :
```json
{
    "payment_intent_id": "pi_xxx",
    "amount": 120.00
}
```

### 3. Rembourser

```http
POST /api/v1/payments/refund
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

**Body** :
```json
{
    "payment_intent_id": "pi_xxx",
    "amount": 50.00,
    "reason": "Annulation client"
}
```

### 4. Token Terminal (Biplaceur)

```http
POST /api/v1/payments/terminal/connection-token
Authorization: Bearer {token} (biplaceur)
```

**Response** (200) :
```json
{
    "success": true,
    "data": {
        "secret": "tok_terminal_xxx"
    }
}
```

### 5. PaymentIntent Terminal

```http
POST /api/v1/payments/terminal/payment-intent
Authorization: Bearer {token} (biplaceur)
Content-Type: application/json
```

**Body** :
```json
{
    "reservation_id": 1,
    "amount": 120.00
}
```

### 6. Créer QR Checkout

```http
POST /api/v1/payments/qr/create
Authorization: Bearer {token} (biplaceur)
Content-Type: application/json
```

**Body** :
```json
{
    "reservation_id": 1,
    "amount": 120.00
}
```

---

## 🪂 Biplaceurs (13 routes)

### Public

#### 1. Liste des Biplaceurs

```http
GET /api/v1/biplaceurs
```

**Query Parameters** :
- `is_active` : Filtrer par actif/inactif
- `search` : Recherche par nom

### Biplaceur Authentifié

#### 2. Mes Vols

```http
GET /api/v1/biplaceurs/me/flights
Authorization: Bearer {token}
```

#### 3. Vols Aujourd'hui

```http
GET /api/v1/biplaceurs/me/flights/today
Authorization: Bearer {token}
```

#### 4. Mon Calendrier

```http
GET /api/v1/biplaceurs/me/calendar
Authorization: Bearer {token}
```

**Query Parameters** :
- `start_date` : Date de début (YYYY-MM-DD)
- `end_date` : Date de fin (YYYY-MM-DD)

#### 5. Mettre à jour Disponibilité

```http
PUT /api/v1/biplaceurs/me/availability
Authorization: Bearer {token}
Content-Type: application/json
```

**Body** :
```json
{
    "availability": {
        "monday": ["09:00", "18:00"],
        "tuesday": ["09:00", "18:00"],
        "wednesday": ["09:00", "18:00"],
        "thursday": ["09:00", "18:00"],
        "friday": ["09:00", "18:00"],
        "saturday": ["08:00", "19:00"],
        "sunday": ["08:00", "19:00"]
    }
}
```

#### 6. Marquer Vol Terminé

```http
POST /api/v1/biplaceurs/me/flights/{id}/mark-done
Authorization: Bearer {token}
```

#### 7. Reporter un Vol

```http
POST /api/v1/biplaceurs/me/flights/{id}/reschedule
Authorization: Bearer {token}
Content-Type: application/json
```

**Body** :
```json
{
    "new_scheduled_at": "2024-06-20T14:00:00Z",
    "reason": "Météo défavorable"
}
```

#### 8. Infos Rapides Vol

```http
GET /api/v1/biplaceurs/me/flights/{id}/quick-info
Authorization: Bearer {token}
```

### Admin

#### 9. Détails Biplaceur

```http
GET /api/v1/biplaceurs/{id}
Authorization: Bearer {token} (admin)
```

#### 10. Calendrier Biplaceur (Admin)

```http
GET /api/v1/biplaceurs/{id}/calendar
Authorization: Bearer {token} (admin)
```

#### 11. Créer Biplaceur

```http
POST /api/v1/biplaceurs
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

#### 12. Modifier Biplaceur

```http
PUT /api/v1/biplaceurs/{id}
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

#### 13. Supprimer Biplaceur

```http
DELETE /api/v1/biplaceurs/{id}
Authorization: Bearer {token} (admin)
```

---

## 👥 Clients (5 routes - Admin)

### 1. Liste des Clients

```http
GET /api/v1/clients
Authorization: Bearer {token} (admin)
```

**Query Parameters** :
- `search` : Recherche par nom/email
- `page` : Numéro de page
- `per_page` : Résultats par page

### 2. Détails Client

```http
GET /api/v1/clients/{id}
Authorization: Bearer {token} (admin)
```

### 3. Créer Client

```http
POST /api/v1/clients
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

### 4. Modifier Client

```http
PUT /api/v1/clients/{id}
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

### 5. Historique Client

```http
GET /api/v1/clients/{id}/history
Authorization: Bearer {token} (admin)
```

---

## 🎁 Options (4 routes)

### Public

#### 1. Liste des Options Disponibles

```http
GET /api/v1/options
```

**Query Parameters** :
- `is_active` : Filtrer par actif/inactif
- `type` : Filtrer par type (photo, video, souvenir, etc.)
- `is_upsellable` : Filtrer les options upsellables

### Admin

#### 2. Créer Option

```http
POST /api/v1/options
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

**Body** :
```json
{
    "code": "PHOTO",
    "name": "Pack Photos",
    "description": "Photos HD du vol",
    "type": "photo",
    "price": 25.00,
    "price_per_participant": false,
    "is_active": true,
    "is_upsellable": true,
    "max_quantity": 1
}
```

#### 3. Modifier Option

```http
PUT /api/v1/options/{id}
Authorization: Bearer {token} (admin)
```

#### 4. Supprimer Option

```http
DELETE /api/v1/options/{id}
Authorization: Bearer {token} (admin)
```

---

## 🎟️ Coupons (4 routes - Admin)

### 1. Liste des Coupons

```http
GET /api/v1/coupons
Authorization: Bearer {token} (admin)
```

### 2. Créer Coupon

```http
POST /api/v1/coupons
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

**Body** :
```json
{
    "code": "SUMMER2024",
    "name": "Réduction été 2024",
    "description": "20% de réduction",
    "discount_type": "percentage",
    "discount_value": 20,
    "min_purchase_amount": 100.00,
    "max_discount": 50.00,
    "valid_from": "2024-06-01",
    "valid_until": "2024-08-31",
    "usage_limit": 100,
    "is_active": true,
    "applicable_flight_types": ["tandem", "initiation"]
}
```

### 3. Modifier Coupon

```http
PUT /api/v1/coupons/{id}
Authorization: Bearer {token} (admin)
```

### 4. Supprimer Coupon

```http
DELETE /api/v1/coupons/{id}
Authorization: Bearer {token} (admin)
```

---

## 🎁 Bons Cadeaux (4 routes)

### Public

#### 1. Valider un Bon Cadeau

```http
POST /api/v1/giftcards/validate
Content-Type: application/json
```

**Body** :
```json
{
    "code": "GIFT123"
}
```

**Response** (200) :
```json
{
    "success": true,
    "data": {
        "id": 1,
        "code": "GIFT123",
        "amount": 100.00,
        "remaining_amount": 100.00,
        "is_valid": true
    }
}
```

### Admin

#### 2. Liste des Bons Cadeaux

```http
GET /api/v1/giftcards
Authorization: Bearer {token} (admin)
```

#### 3. Créer Bon Cadeau

```http
POST /api/v1/giftcards
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

**Body** :
```json
{
    "code": "GIFT123",
    "amount": 100.00,
    "expires_at": "2025-12-31",
    "is_active": true
}
```

#### 4. Modifier Bon Cadeau

```http
PUT /api/v1/giftcards/{id}
Authorization: Bearer {token} (admin)
```

---

## ✍️ Signatures (1 route)

### Enregistrer Signature

```http
POST /api/v1/signatures/{reservation_id}
Content-Type: application/json
```

**Body** :
```json
{
    "signature": "data:image/png;base64,iVBORw0KG...",
    "signer_name": "Jean Dupont",
    "signer_email": "jean@example.com"
}
```

---

## 📊 Dashboard Admin (6 routes)

### 1. Dashboard Principal

```http
GET /api/v1/admin/dashboard
Authorization: Bearer {token} (admin)
```

### 2. Résumé Global

```http
GET /api/v1/admin/dashboard/summary
Authorization: Bearer {token} (admin)
```

### 3. Statistiques

```http
GET /api/v1/admin/dashboard/stats
Authorization: Bearer {token} (admin)
```

### 4. Revenus

```http
GET /api/v1/admin/dashboard/revenue
Authorization: Bearer {token} (admin)
```

**Query Parameters** :
- `start_date` : Date de début (YYYY-MM-DD)
- `end_date` : Date de fin (YYYY-MM-DD)

### 5. Statistiques Vols

```http
GET /api/v1/admin/dashboard/flights
Authorization: Bearer {token} (admin)
```

### 6. Top Biplaceurs

```http
GET /api/v1/admin/dashboard/top-biplaceurs
Authorization: Bearer {token} (admin)
```

**Query Parameters** :
- `start_date` : Date de début
- `end_date` : Date de fin
- `limit` : Nombre de résultats (défaut: 10)

---

## 🔧 Admin - Réservations (10 routes)

### 1. Liste des Réservations

```http
GET /api/v1/admin/reservations
Authorization: Bearer {token} (admin)
```

**Query Parameters** :
- `status` : pending, authorized, scheduled, confirmed, completed, cancelled, rescheduled, refunded
- `payment_status` : pending, authorized, partially_captured, captured, failed, refunded
- `flight_type` : tandem, biplace, initiation, perfectionnement, autonome
- `instructor_id` : ID du moniteur
- `date_from` : Date de début (YYYY-MM-DD)
- `date_to` : Date de fin (YYYY-MM-DD)
- `search` : Recherche texte (email, nom, UUID)
- `page` : Numéro de page
- `per_page` : Résultats par page (défaut: 15)

### 2. Détails Réservation

```http
GET /api/v1/admin/reservations/{id}
Authorization: Bearer {token} (admin)
```

### 3. Historique Réservation

```http
GET /api/v1/admin/reservations/{id}/history
Authorization: Bearer {token} (admin)
```

### 4. Planifier Réservation

```http
POST /api/v1/admin/reservations/{id}/schedule
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

**Body** :
```json
{
    "scheduled_at": "2024-06-15T14:00:00Z",
    "instructor_id": 5,
    "site_id": 1,
    "tandem_glider_id": 3,
    "vehicle_id": 2
}
```

**Contraintes validées** :
- Limite de vols par jour du biplaceur (5 max par défaut)
- Pause obligatoire de 30 min entre vols
- Certifications biplaceur pour options
- Capacité navette (8 passagers max)
- Poids total navette

### 5. Assigner Ressources

```http
PUT /api/v1/admin/reservations/{id}/assign
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

**Body** : Identique à `/schedule`

### 6. Mettre à jour Statut

```http
PATCH /api/v1/admin/reservations/{id}/status
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

**Body** :
```json
{
    "status": "confirmed",
    "notes": "Confirmé par email"
}
```

### 7. Ajouter Options

```http
POST /api/v1/admin/reservations/{id}/add-options
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

### 8. Marquer comme Complété

```http
POST /api/v1/admin/reservations/{id}/complete
Authorization: Bearer {token} (admin)
```

### 9. Capturer Paiement

```http
POST /api/v1/admin/reservations/{id}/capture
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

**Body** :
```json
{
    "amount": 120.00
}
```

**Note** : Si `amount` non fourni, capture du montant total.

### 10. Rembourser

```http
POST /api/v1/admin/reservations/{id}/refund
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

**Body** :
```json
{
    "amount": 50.00,
    "reason": "Annulation client"
}
```

---

## 🔗 Gestion des Ressources (8 routes - Admin)

### 1. Liste des Ressources

```http
GET /api/v1/admin/resources
Authorization: Bearer {token} (admin)
```

**Query Parameters** :
- `type` : vehicle, tandem_glider, equipment
- `is_active` : Filtrer par actif/inactif
- `search` : Recherche par nom/code

### 2. Détails Ressource

```http
GET /api/v1/admin/resources/{id}
Authorization: Bearer {token} (admin)
```

### 3. Créer Ressource

```http
POST /api/v1/admin/resources
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

**Body** (Navette) :
```json
{
    "code": "NAV-001",
    "name": "Navette 1",
    "type": "vehicle",
    "description": "Navette 9 places",
    "specifications": {
        "capacity": 9,
        "weight_limit": 450
    },
    "is_active": true
}
```

**Body** (Tandem Glider) :
```json
{
    "code": "TG-001",
    "name": "Tandem Glider 1",
    "type": "tandem_glider",
    "description": "Voile tandem",
    "specifications": {
        "max_weight": 180,
        "wing_size": 32.5
    },
    "is_active": true
}
```

### 4. Modifier Ressource

```http
PUT /api/v1/admin/resources/{id}
Authorization: Bearer {token} (admin)
```

### 5. Supprimer Ressource

```http
DELETE /api/v1/admin/resources/{id}
Authorization: Bearer {token} (admin)
```

**Note** : Si la ressource est utilisée dans des réservations, elle sera désactivée (soft delete).

### 6. Liste Navettes

```http
GET /api/v1/admin/resources/vehicles
Authorization: Bearer {token} (admin)
```

### 7. Liste Tandem Gliders

```http
GET /api/v1/admin/resources/tandem-gliders
Authorization: Bearer {token} (admin)
```

### 8. Ressources Disponibles

```http
GET /api/v1/admin/resources/available
Authorization: Bearer {token} (admin)
```

**Query Parameters** :
- `type` : vehicle, tandem_glider, equipment (requis)
- `date` : Date (YYYY-MM-DD) (requis)
- `time` : Heure (HH:MM) (optionnel)

---

## 📍 Sites (5 routes)

### Public

#### 1. Liste des Sites

```http
GET /api/v1/sites
```

**Query Parameters** :
- `is_active` : Filtrer par actif/inactif
- `difficulty_level` : beginner, intermediate, advanced
- `search` : Recherche par nom/description

#### 2. Détails Site

```http
GET /api/v1/sites/{id}
```

### Admin

#### 3. Créer Site

```http
POST /api/v1/sites
Authorization: Bearer {token} (admin)
Content-Type: application/json
```

**Body** :
```json
{
    "code": "SITE-001",
    "name": "Site de décollage A",
    "description": "Site principal avec vue panoramique",
    "location": "Alpes, France",
    "latitude": 45.1234,
    "longitude": 6.5678,
    "altitude": 1500,
    "difficulty_level": "intermediate",
    "orientation": "S",
    "wind_conditions": "Vent d'ouest favorable",
    "is_active": true
}
```

#### 4. Modifier Site

```http
PUT /api/v1/sites/{id}
Authorization: Bearer {token} (admin)
```

#### 5. Supprimer Site

```http
DELETE /api/v1/sites/{id}
Authorization: Bearer {token} (admin)
```

**Note** : Si le site est utilisé dans des réservations, il sera désactivé (soft delete).

---

## 📧 Notifications (5 routes - Authentifié)

### 1. Liste des Notifications

```http
GET /api/v1/notifications
Authorization: Bearer {token}
```

**Query Parameters** :
- `type` : email, sms, push
- `status` : pending, sent, failed
- `reservation_id` : Filtrer par réservation
- `unread_only` : true/false (filtrer non lues)
- `page` : Numéro de page
- `per_page` : Résultats par page

### 2. Détails Notification

```http
GET /api/v1/notifications/{id}
Authorization: Bearer {token}
```

### 3. Marquer comme Lue

```http
POST /api/v1/notifications/{id}/read
Authorization: Bearer {token}
```

### 4. Marquer Toutes comme Lues

```http
POST /api/v1/notifications/mark-all-read
Authorization: Bearer {token}
```

**Response** (200) :
```json
{
    "success": true,
    "message": "3 notifications marquées comme lues",
    "count": 3
}
```

### 5. Compter les Non Lues

```http
GET /api/v1/notifications/unread-count
Authorization: Bearer {token}
```

**Response** (200) :
```json
{
    "success": true,
    "data": {
        "unread_count": 5
    }
}
```

---

## 📊 Rapports (3 routes - Admin)

### 1. Liste des Rapports

```http
GET /api/v1/admin/reports
Authorization: Bearer {token} (admin)
```

**Query Parameters** :
- `date_from` : Date de début (YYYY-MM-DD)
- `date_to` : Date de fin (YYYY-MM-DD)
- `page` : Numéro de page
- `per_page` : Résultats par page

### 2. Rapport Quotidien

```http
GET /api/v1/admin/reports/daily
Authorization: Bearer {token} (admin)
```

**Query Parameters** :
- `date` : Date (YYYY-MM-DD) (défaut: aujourd'hui)

**Response** (200) :
```json
{
    "success": true,
    "data": {
        "date": "2024-06-15",
        "stats": {
            "reservations": 10,
            "scheduled": 8,
            "completed": 6,
            "cancelled": 1
        },
        "revenue": 1200.00,
        "yesterday_revenue": 1100.00,
        "revenue_evolution": "9.1%"
    }
}
```

### 3. Rapport Mensuel

```http
GET /api/v1/admin/reports/monthly
Authorization: Bearer {token} (admin)
```

**Query Parameters** :
- `month` : Mois (YYYY-MM) (défaut: mois actuel)

**Response** (200) :
```json
{
    "success": true,
    "data": {
        "month": "2024-06",
        "total_revenue": 36000.00,
        "daily_breakdown": [
            {
                "date": "2024-06-01",
                "revenue": 1200.00
            },
            {
                "date": "2024-06-02",
                "revenue": 1500.00
            }
        ]
    }
}
```

---

## 🔗 Webhooks Stripe

### Endpoint

```http
POST /api/webhooks/stripe
```

**Headers requis** :
```
Stripe-Signature: t=timestamp,v1=signature
Content-Type: application/json
```

**Événements gérés** :
- `payment_intent.succeeded` : Paiement réussi
- `payment_intent.payment_failed` : Paiement échoué
- `payment_intent.requires_capture` : Capture requise
- `payment_intent.canceled` : Paiement annulé
- `charge.refunded` : Remboursement effectué
- `setup_intent.succeeded` : SetupIntent réussi (sauvegarde carte)

**Configuration** : Configurer l'URL du webhook dans le dashboard Stripe.

---

## Codes d'Erreur

| Code | Description |
|------|-------------|
| 200 | Succès |
| 201 | Créé avec succès |
| 400 | Erreur de validation |
| 401 | Non authentifié |
| 403 | Non autorisé |
| 404 | Ressource non trouvée |
| 422 | Erreur de validation (détails) |
| 500 | Erreur serveur |

**Format d'erreur** :
```json
{
    "success": false,
    "message": "Message d'erreur",
    "errors": {
        "field": ["Erreur de validation"]
    }
}
```

---

## Statuts de Réservation

| Statut | Description |
|--------|-------------|
| `pending` | En attente d'assignation |
| `authorized` | Paiement autorisé (empreinte/acompte) |
| `scheduled` | Date assignée |
| `confirmed` | Confirmée par client |
| `completed` | Vol effectué |
| `cancelled` | Annulée |
| `rescheduled` | Reportée |
| `refunded` | Remboursée |

## Statuts de Paiement

| Statut | Description |
|--------|-------------|
| `pending` | En attente |
| `authorized` | Autorisé (non capturé) |
| `partially_captured` | Partiellement capturé |
| `captured` | Capturé |
| `failed` | Échoué |
| `refunded` | Remboursé |

---

## Contraintes Métier

### Clients
- **Poids** : 40-120 kg
- **Taille** : Minimum 1.40m (140 cm)

### Biplaceurs
- **Limite de vols/jour** : 5 maximum (configurable)
- **Pause obligatoire** : 30 minutes entre vols
- **Certifications** : Requises pour certaines options (photo, video)

### Navettes
- **Capacité** : 9 places totales (1 chauffeur + 8 passagers)
- **Poids total** : 450 kg maximum

---

## Exemples d'Intégration

### JavaScript (Frontend)

```javascript
// Configuration
const API_BASE_URL = 'http://localhost:8000/api/v1';
let authToken = null;

// Fonction helper pour les requêtes
async function apiRequest(endpoint, options = {}) {
    const url = `${API_BASE_URL}${endpoint}`;
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers
    };
    
    if (authToken) {
        headers['Authorization'] = `Bearer ${authToken}`;
    }
    
    const response = await fetch(url, {
        ...options,
        headers
    });
    
    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Erreur API');
    }
    
    return await response.json();
}

// Connexion
async function login(email, password) {
    const response = await apiRequest('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password })
    });
    
    authToken = response.data.token;
    return response.data.user;
}

// Créer réservation
async function createReservation(data) {
    return await apiRequest('/reservations', {
        method: 'POST',
        body: JSON.stringify(data)
    });
}

// Liste des réservations (client)
async function getMyReservations() {
    return await apiRequest('/my/reservations');
}

// Utilisation
(async () => {
    try {
        await login('user@example.com', 'password');
        const reservations = await getMyReservations();
        console.log(reservations);
    } catch (error) {
        console.error('Erreur:', error);
    }
})();
```

### PHP (Backend)

```php
use Illuminate\Support\Facades\Http;

$baseUrl = 'http://localhost:8000/api/v1';
$token = 'your-token-here';

// Créer réservation
$response = Http::withToken($token)
    ->post("{$baseUrl}/reservations", [
        'customer_email' => 'client@example.com',
        'customer_first_name' => 'Jean',
        'customer_last_name' => 'Dupont',
        'flight_type' => 'tandem',
        'participants_count' => 1,
        'payment_method_id' => 'pm_xxx',
        'payment_type' => 'deposit'
    ]);

if ($response->successful()) {
    $data = $response->json();
    echo "Réservation créée: " . $data['data']['reservation']['uuid'];
} else {
    echo "Erreur: " . $response->body();
}
```

### Python

```python
import requests

BASE_URL = 'http://localhost:8000/api/v1'
token = None

def login(email, password):
    global token
    response = requests.post(f'{BASE_URL}/auth/login', json={
        'email': email,
        'password': password
    })
    response.raise_for_status()
    data = response.json()
    token = data['data']['token']
    return data['data']['user']

def create_reservation(data):
    headers = {'Authorization': f'Bearer {token}'}
    response = requests.post(f'{BASE_URL}/reservations', json=data, headers=headers)
    response.raise_for_status()
    return response.json()

# Utilisation
user = login('user@example.com', 'password')
reservation = create_reservation({
    'customer_email': 'client@example.com',
    'customer_first_name': 'Jean',
    'customer_last_name': 'Dupont',
    'flight_type': 'tandem',
    'participants_count': 1,
    'payment_method_id': 'pm_xxx',
    'payment_type': 'deposit'
})
```

---

## Rate Limiting

L'API applique des limites de taux par défaut :
- **Public endpoints** : 60 requêtes/minute
- **Authenticated endpoints** : 100 requêtes/minute
- **Admin endpoints** : 200 requêtes/minute

Les headers de réponse incluent :
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1633024800
```

---

## Pagination

Toutes les listes utilisent la pagination Laravel :

**Query Parameters** :
- `page` : Numéro de page (défaut: 1)
- `per_page` : Résultats par page (défaut: 15, max: 100)

**Response Format** :
```json
{
    "success": true,
    "data": {
        "data": [...],
        "current_page": 1,
        "per_page": 15,
        "total": 100,
        "last_page": 7,
        "from": 1,
        "to": 15
    }
}
```

---

## Versioning

L'API est versionnée via l'URL : `/api/v1/`

Les versions futures seront accessibles via `/api/v2/`, `/api/v3/`, etc.

---

**Dernière mise à jour** : 2025-11-05  
**Version API** : 1.0.0  
**Total d'endpoints** : 96 routes
