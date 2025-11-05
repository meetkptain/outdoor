# 🏗️ Architecture Complète - Système de Gestion Parapente

## 📐 Vue d'Ensemble

Système complet de gestion pour club de parapente avec :
- **Multi-rôles** : Admin, Biplaceur, Client
- **Paiements avancés** : Stripe avec capture différée, Tap to Pay, QR code
- **Gestion complète** : Vols, biplaceurs, clients, paiements, options, reports
- **Mobile-ready** : API optimisée pour app Flutter biplaceurs
- **Architecture modulaire** : DDD / Service-based, évolutive

---

## 🎯 Rôles & Permissions

### Rôle Admin
- Accès complet au système
- Gestion des réservations, biplaceurs, clients
- Dashboard analytique
- Gestion des paiements et remboursements
- Configuration des options, coupons, bons cadeaux

### Rôle Biplaceur
- Voir ses propres vols (liste + calendrier)
- Voir les infos clients (poids, taille, options, remarques)
- Encaisser sur place (Tap to Pay / QR Stripe)
- Marquer vol comme "fait", "reporté", "annulé"
- Gérer ses disponibilités
- Recevoir notifications push

### Rôle Client
- Voir ses propres réservations
- Ajouter des options après réservation
- Reporter/annuler son vol (si autorisé)
- Consulter factures et reçus
- Utiliser bons cadeaux

---

## 🗄️ Structure de Base de Données

### Tables Principales

#### `users`
- Authentification multi-rôles (admin, biplaceur, client)
- Email, password, nom, prénom
- Timestamps, soft deletes

#### `clients`
- Extension de `users` pour les clients
- Téléphone, poids, taille, remarques médicales
- Historique des vols

#### `biplaceurs`
- Extension de `users` pour les biplaceurs
- Expérience, certifications, disponibilités (JSON)
- Statut (actif/inactif)

#### `reservations`
- Entité centrale du système
- Statuts : `pending`, `authorized`, `scheduled`, `paid`, `rescheduled`, `cancelled`, `completed`
- Relations : client_id, biplaceur_id, paiements, options

#### `payments`
- Types : `deposit`, `authorization`, `capture`, `refund`
- Intégration Stripe complète
- Support Tap to Pay et QR code

#### `options`
- Photo, vidéo, durée, cadeau, etc.
- Prix dynamique, actif/inactif

#### `reservation_options`
- Table pivot avec quantités et prix historiques

#### `coupons`
- Codes promo avec règles (montant min, validité, usage limit)

#### `gift_cards`
- Bons cadeaux avec solde et validité

#### `reports`
- Reports météo ou autres raisons
- Historique des reports

#### `signatures`
- Signatures électroniques (décharges)
- Hash + fichier

#### `notifications`
- Notifications in-app pour tous les rôles
- Email, SMS, push (prévu)

---

## 🔄 Flux Métier Complets

### 1. Réservation Client (Site Statique → API)

```
1. Client remplit formulaire (nom, email, vol, options optionnelles)
   ↓
2. API Laravel crée réservation en statut "pending"
   ↓
3. Création PaymentIntent Stripe (capture_method: manual)
   ↓
4. Client paie acompte ou empreinte bancaire
   ↓
5. Statut passe à "authorized"
   ↓
6. Email confirmation envoyé
```

### 2. Planification par le Club

```
1. Admin valide/planifie la date du vol
   ↓
2. Assignation biplaceur + site + ressources
   ↓
3. Statut passe à "scheduled"
   ↓
4. Email + SMS notification au client
   ↓
5. Notification push au biplaceur (si app mobile)
```

### 3. Jour du Vol (Biplaceur)

```
1. Biplaceur ouvre app mobile / back-office
   ↓
2. Voir ses vols du jour avec infos client
   ↓
3. Encaisser solde sur place (Tap to Pay ou QR)
   ↓
4. Capture Stripe automatique
   ↓
5. Marquer vol comme "fait" → statut "completed"
   ↓
6. Facture automatique envoyée
```

### 4. Report Météo

```
1. Biplaceur ou Admin marque comme "reporté"
   ↓
2. Statut passe à "rescheduled"
   ↓
3. Client notifié (email + SMS)
   ↓
4. Nouvelle date à planifier
```

### 5. Annulation

```
1. Client ou Admin annule
   ↓
2. Statut passe à "cancelled"
   ↓
3. Remboursement Stripe selon politique
   ↓
4. Notification envoyée
```

---

## 💳 Intégration Stripe Avancée

### Types de Paiements Supportés

1. **Empreinte bancaire** (SetupIntent)
   - Sauvegarde méthode de paiement
   - Pas de capture immédiate

2. **Acompte** (PaymentIntent partiel)
   - Montant partiel capturé immédiatement
   - Reste à capturer après le vol

3. **Paiement complet**
   - Capture immédiate

4. **Capture différée** (après le vol)
   - Authorization initiale
   - Capture après vol

5. **Paiement sur place**
   - **Stripe Terminal SDK** (Tap to Pay / NFC)
   - **QR code Checkout** (fallback)

6. **Remboursement / Avoirs**
   - Remboursement total ou partiel
   - Avoirs pour futurs vols

### Webhooks Stripe à Gérer

- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `payment_intent.canceled`
- `charge.refunded`
- `setup_intent.succeeded`
- `payment_intent.requires_capture`

---

## 🏛️ Architecture Technique (DDD / Service-Based)

### Structure des Dossiers

```
app/
├── Domain/
│   ├── Users/
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Biplaceur.php
│   │   │   └── Client.php
│   │   └── Repositories/
│   ├── Reservations/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── DTOs/
│   │   └── Events/
│   ├── Payments/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Stripe/
│   └── Notifications/
│       ├── Services/
│       └── Channels/
├── Application/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   └── v1/
│   │   │   │       ├── AuthController.php
│   │   │   │       ├── ReservationController.php
│   │   │   │       ├── PaymentController.php
│   │   │   │       ├── BiplaceurController.php
│   │   │   │       ├── ClientController.php
│   │   │   │       ├── DashboardController.php
│   │   │   │       └── Admin/
│   │   │   └── Webhook/
│   │   ├── Requests/
│   │   │   ├── CreateReservationRequest.php
│   │   │   ├── AddOptionsRequest.php
│   │   │   └── ...
│   │   └── Middleware/
│   │       ├── RoleMiddleware.php
│   │       └── VerifyStripeWebhook.php
│   └── Services/
│       ├── ReservationService.php
│       ├── PaymentService.php
│       ├── BiplaceurService.php
│       ├── ClientService.php
│       ├── DashboardService.php
│       └── StripeTerminalService.php
├── Infrastructure/
│   ├── Stripe/
│   │   ├── StripeClient.php
│   │   └── TerminalService.php
│   └── Notifications/
│       ├── EmailService.php
│       └── SmsService.php
└── Events/
    ├── ReservationCreated.php
    ├── ReservationScheduled.php
    ├── PaymentCaptured.php
    └── ...
```

### Design Patterns Utilisés

1. **Service Layer** : Logique métier dans les services
2. **Repository Pattern** : Accès données abstrait (si besoin)
3. **DTOs** : Data Transfer Objects pour APIs
4. **Events & Listeners** : Événements métier
5. **Factory Pattern** : Création objets complexes

---

## 🔌 API Endpoints Complets

### Authentification

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| POST | `/api/v1/auth/register` | Créer compte client | Public |
| POST | `/api/v1/auth/login` | Connexion | Public |
| POST | `/api/v1/auth/logout` | Déconnexion | Sanctum |
| GET | `/api/v1/auth/me` | Profil utilisateur | Sanctum |

### Réservations (Public)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/v1/reservations` | Créer réservation |
| GET | `/api/v1/reservations/{uuid}` | Suivre réservation |
| POST | `/api/v1/reservations/{uuid}/add-options` | Ajouter options |
| POST | `/api/v1/reservations/{uuid}/apply-coupon` | Appliquer coupon |
| POST | `/api/v1/reservations/{uuid}/reschedule` | Reporter vol |
| POST | `/api/v1/reservations/{uuid}/cancel` | Annuler vol |

### Réservations (Client Authentifié)

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| GET | `/api/v1/my/reservations` | Mes réservations | Client |
| GET | `/api/v1/my/reservations/{id}` | Détails réservation | Client |
| POST | `/api/v1/my/reservations/{id}/add-options` | Ajouter options | Client |

### Réservations (Admin)

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| GET | `/api/v1/admin/reservations` | Liste réservations | Admin |
| GET | `/api/v1/admin/reservations/{id}` | Détails réservation | Admin |
| PUT | `/api/v1/admin/reservations/{id}/schedule` | Planifier date | Admin |
| PUT | `/api/v1/admin/reservations/{id}/assign` | Assigner biplaceur | Admin |
| PATCH | `/api/v1/admin/reservations/{id}/status` | Changer statut | Admin |
| POST | `/api/v1/admin/reservations/{id}/add-options` | Ajouter options | Admin |
| POST | `/api/v1/admin/reservations/{id}/complete` | Marquer complété | Admin |

### Paiements

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| POST | `/api/v1/payments/intent` | Créer PaymentIntent | Public/Client |
| POST | `/api/v1/payments/capture` | Capturer paiement | Admin/Biplaceur |
| POST | `/api/v1/payments/refund` | Rembourser | Admin |
| POST | `/api/v1/payments/terminal/connection-token` | Token Terminal Stripe | Biplaceur |
| POST | `/api/v1/payments/qr/create` | Créer QR Checkout | Biplaceur |

### Biplaceurs

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| GET | `/api/v1/biplaceurs` | Liste biplaceurs | Admin |
| GET | `/api/v1/biplaceurs/{id}` | Détails biplaceur | Admin |
| POST | `/api/v1/biplaceurs` | Créer biplaceur | Admin |
| PUT | `/api/v1/biplaceurs/{id}` | Modifier biplaceur | Admin |
| GET | `/api/v1/biplaceurs/me/flights` | Mes vols | Biplaceur |
| GET | `/api/v1/biplaceurs/me/flights/today` | Vols du jour | Biplaceur |
| GET | `/api/v1/biplaceurs/me/calendar` | Calendrier | Biplaceur |
| PUT | `/api/v1/biplaceurs/me/availability` | Mettre à jour disponibilités | Biplaceur |
| POST | `/api/v1/biplaceurs/me/flights/{id}/mark-done` | Marquer vol fait | Biplaceur |
| POST | `/api/v1/biplaceurs/me/flights/{id}/reschedule` | Reporter vol | Biplaceur |

### Clients

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| GET | `/api/v1/clients` | Liste clients | Admin |
| GET | `/api/v1/clients/{id}` | Détails client | Admin |
| PUT | `/api/v1/clients/{id}` | Modifier client | Admin |
| GET | `/api/v1/clients/{id}/history` | Historique client | Admin |

### Options

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| GET | `/api/v1/options` | Liste options | Public |
| POST | `/api/v1/admin/options` | Créer option | Admin |
| PUT | `/api/v1/admin/options/{id}` | Modifier option | Admin |

### Coupons & Bons Cadeaux

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| GET | `/api/v1/admin/coupons` | Liste coupons | Admin |
| POST | `/api/v1/admin/coupons` | Créer coupon | Admin |
| POST | `/api/v1/giftcards/validate` | Valider bon cadeau | Public |
| POST | `/api/v1/admin/giftcards` | Créer bon cadeau | Admin |

### Dashboard

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| GET | `/api/v1/admin/dashboard/summary` | Résumé global | Admin |
| GET | `/api/v1/admin/dashboard/revenue` | Revenus | Admin |
| GET | `/api/v1/admin/dashboard/flights` | Statistiques vols | Admin |

### Signatures

| Méthode | Endpoint | Description | Auth |
|---------|----------|-------------|------|
| POST | `/api/v1/signatures/{reservation_id}` | Upload signature | Public/Client |

### Webhooks

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/webhooks/stripe` | Webhooks Stripe |

---

## 📊 Services Métier

### ReservationService

**Responsabilités** :
- Création réservations
- Calcul des montants (base + options - réductions)
- Gestion des statuts
- Assignation dates et ressources
- Reports et annulations

**Méthodes principales** :
- `createReservation(array $data): Reservation`
- `scheduleReservation(Reservation $reservation, array $data): Reservation`
- `addOptions(Reservation $reservation, array $options): Reservation`
- `rescheduleReservation(Reservation $reservation, string $reason): Reservation`
- `cancelReservation(Reservation $reservation, string $reason): Reservation`

### PaymentService

**Responsabilités** :
- Création PaymentIntent Stripe
- Capture différée
- Remboursements
- Gestion SetupIntent (empreintes)
- Réautorisation si expiration

**Méthodes principales** :
- `createPaymentIntent(Reservation $reservation, float $amount, string $type): Payment`
- `capturePayment(Payment $payment, ?float $amount = null): bool`
- `refundPayment(Payment $payment, ?float $amount = null, string $reason = null): bool`
- `createSetupIntent(string $customerEmail): SetupIntent`
- `reauthorizeIfNeeded(Reservation $reservation): ?Payment`

### StripeTerminalService

**Responsabilités** :
- Génération connection token Stripe Terminal
- Gestion Tap to Pay
- Création QR code Checkout
- Synchronisation paiements terminaux

**Méthodes principales** :
- `getConnectionToken(): string`
- `processTerminalPayment(string $paymentIntentId, array $metadata): Payment`
- `createQrCheckout(Reservation $reservation, float $amount): array`

### BiplaceurService

**Responsabilités** :
- Récupération vols biplaceur
- Gestion disponibilités
- Assignation automatique (futur)
- Statistiques biplaceur

**Méthodes principales** :
- `getFlightsToday(int $biplaceurId): Collection`
- `getCalendar(int $biplaceurId, string $startDate, string $endDate): Collection`
- `updateAvailability(int $biplaceurId, array $availability): bool`
- `markFlightDone(int $reservationId, int $biplaceurId): Reservation`

### ClientService

**Responsabilités** :
- Création comptes clients
- Gestion profils clients
- Historique des vols
- Gestion bons cadeaux

**Méthodes principales** :
- `createClient(array $data): Client`
- `getClientHistory(int $clientId): Collection`
- `applyGiftCard(int $reservationId, string $giftCardCode): bool`

### DashboardService

**Responsabilités** :
- Calcul CA (chiffre d'affaires)
- Taux de vols effectués
- Top biplaceurs
- Statistiques par période

**Méthodes principales** :
- `getSummary(string $period = 'month'): array`
- `getRevenue(string $startDate, string $endDate): array`
- `getTopBiplaceurs(int $limit = 10): Collection`
- `getFlightStats(string $period = 'month'): array`

### NotificationService

**Responsabilités** :
- Envoi emails
- Envoi SMS
- Notifications push (futur)
- Gestion templates

**Méthodes principales** :
- `sendReservationConfirmation(Reservation $reservation): void`
- `sendAssignmentNotification(Reservation $reservation): void`
- `sendReminder(Reservation $reservation): void`
- `sendThankYou(Reservation $reservation): void`
- `notifyBiplaceur(Reservation $reservation, string $type): void`

---

## 🔐 Authentification & Sécurité

### Laravel Sanctum

- **Tokens API** pour biplaceurs et clients
- **Session** pour admin (optionnel)
- **Expiration tokens** : 7 jours (configurable)

### Middleware

- `auth:sanctum` : Authentification requise
- `role:admin` : Rôle admin requis
- `role:biplaceur` : Rôle biplaceur requis
- `role:client` : Rôle client requis
- `verify.stripe.webhook` : Vérification signature Stripe

### Rate Limiting

- Endpoints publics : 60 req/min
- Endpoints authentifiés : 120 req/min
- Webhooks : Pas de limite (signature vérifiée)

---

## 📱 Optimisation Mobile (Flutter)

### Endpoints Optimisés

- **Format JSON compact** : Pas de relations inutiles
- **Pagination** : Limite 20 items par défaut
- **Cache-friendly** : Headers ETag, Last-Modified
- **Compression** : Gzip activé

### Endpoints Spéciaux Biplaceurs

- `/api/v1/biplaceurs/me/flights/today` : Vols du jour uniquement
- `/api/v1/biplaceurs/me/calendar` : Calendrier format optimisé
- `/api/v1/biplaceurs/me/flights/{id}/quick-info` : Infos client rapides

### Synchronisation Offline (Futur)

- Mode offline : Cache local
- Sync automatique : Quand connexion retrouvée
- Conflits : Résolution manuelle

---

## 🧪 Tests

### Unitaires

- Services métier
- Modèles (relations, scopes)
- DTOs et validations

### Intégration

- Flux réservation complet
- Intégration Stripe (mock)
- Webhooks Stripe
- Authentification multi-rôles

### Feature Tests

- Parcours client complet
- Parcours biplaceur
- Parcours admin
- Gestion paiements

---

## 📅 Roadmap de Développement

### Phase 1 - Fondations (Semaine 1-2)

- [x] Structure de base Laravel
- [ ] Migrations complètes (users, clients, biplaceurs, reservations, payments, etc.)
- [ ] Modèles avec relations
- [ ] Authentification multi-rôles (Sanctum)
- [ ] Middleware rôles
- [ ] Services de base (ReservationService, PaymentService)

### Phase 2 - API Core (Semaine 3-4)

- [ ] Endpoints authentification
- [ ] Endpoints réservations (public + admin)
- [ ] Endpoints paiements (Stripe de base)
- [ ] Webhooks Stripe
- [ ] Notifications email/SMS

### Phase 3 - Gestion Club (Semaine 5-6)

- [ ] Endpoints biplaceurs
- [ ] Endpoints clients
- [ ] Dashboard admin
- [ ] Gestion options dynamiques
- [ ] Coupons et bons cadeaux

### Phase 4 - Paiements Avancés (Semaine 7)

- [ ] Stripe Terminal (Tap to Pay)
- [ ] QR code Checkout
- [ ] Capture différée complète
- [ ] Remboursements

### Phase 5 - Fonctionnalités Avancées (Semaine 8)

- [ ] Signatures électroniques
- [ ] Reports météo
- [ ] Export PDF/CSV
- [ ] Notifications push (préparation)
- [ ] Tests complets

---

## 🚀 Déploiement

### Prérequis Production

- PHP 8.2+
- PostgreSQL 14+
- Redis
- Composer
- Node.js 18+ (si frontend)

### Variables d'Environnement

```env
# App
APP_NAME="Parapente Club"
APP_ENV=production
APP_DEBUG=false

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=parapente
DB_USERNAME=...
DB_PASSWORD=...

# Stripe
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Queue
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

# Mail
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=...
MAILGUN_SECRET=...

# SMS
TWILIO_SID=...
TWILIO_TOKEN=...
TWILIO_FROM=...
```

### Checklist Production

- [ ] Migrations exécutées
- [ ] Webhook Stripe configuré
- [ ] Queue workers actifs
- [ ] Scheduler cron configuré
- [ ] HTTPS activé
- [ ] Monitoring/Logging configuré
- [ ] Backup DB automatique
- [ ] Rate limiting configuré
- [ ] Cache optimisé

---

## 📚 Documentation API

### Swagger / OpenAPI

- Génération automatique depuis annotations
- Disponible sur `/api/documentation`
- Exemples de requêtes/réponses

### Postman Collection

- Collection complète exportable
- Variables d'environnement
- Tests automatiques

---

## 🔄 Évolutions Futures

1. **Météo Intégrée**
   - API météo automatique
   - Alertes conditions défavorables
   - Reports automatiques

2. **RFID**
   - Badges clients
   - Check-in automatique
   - Suivi équipements

3. **Application Mobile Biplaceurs**
   - Flutter app complète
   - Mode offline
   - Notifications push

4. **Rapports Avancés**
   - Analytics temps réel
   - Prédictions
   - Export Excel/PDF avancés

5. **Multi-clubs**
   - Architecture multi-tenant
   - Gestion centralisée
   - Isolation des données

---

**Document créé** : Architecture complète selon spécifications
**Version** : 1.0.0
