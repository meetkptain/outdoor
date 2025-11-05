# 🪂 Système de Réservation Parapente Premium

Système complet de réservation pour club de parapente avec paiement en deux temps (empreinte/acompte + capture post-vol), gestion de ressources, upsell d'options et back-office complet.

## 📋 Architecture Technique

### Stack
- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Vue.js 3 + Inertia.js (back-office) + HTML/JS widgets (public)
- **Base de données**: PostgreSQL
- **Paiements**: Stripe (PaymentIntent avec `manual_capture`)
- **File d'attente**: Redis + Laravel Queue
- **Stockage**: S3-compatible
- **Notifications**: Mailgun (email) + Twilio (SMS)

### Structure du Projet

```
parapente/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── v1/
│   │   │   │   │   ├── ReservationController.php
│   │   │   │   │   ├── PaymentController.php
│   │   │   │   │   └── Admin/
│   │   │   │   │       ├── ReservationAdminController.php
│   │   │   │   │       ├── ResourceController.php
│   │   │   │   │       └── DashboardController.php
│   │   │   └── Webhook/
│   │   │       └── StripeWebhookController.php
│   │   └── Requests/
│   ├── Models/
│   │   ├── Reservation.php
│   │   ├── Flight.php
│   │   ├── Payment.php
│   │   ├── Option.php
│   │   ├── Resource.php
│   │   ├── Coupon.php
│   │   └── GiftCard.php
│   ├── Services/
│   │   ├── PaymentService.php
│   │   ├── ReservationService.php
│   │   ├── NotificationService.php
│   │   └── UpsellService.php
│   └── Jobs/
│       ├── SendReservationConfirmation.php
│       ├── SendReminder.php
│       └── CapturePayment.php
├── database/
│   └── migrations/
├── resources/
│   ├── js/
│   │   ├── Pages/
│   │   │   ├── Admin/
│   │   │   └── Public/
│   │   └── Components/
│   └── views/
│       └── emails/
├── routes/
│   ├── api.php
│   └── web.php
└── tests/
```

## 🚀 Installation

### Prérequis
- PHP 8.2+
- Composer
- PostgreSQL 14+
- Redis
- Node.js 18+

### Étapes

1. **Installation des dépendances**
```bash
composer install
npm install
```

2. **Configuration**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Variables d'environnement importantes**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=parapente
DB_USERNAME=postgres
DB_PASSWORD=

STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis

MAIL_MAILER=mailgun
MAILGUN_DOMAIN=...
MAILGUN_SECRET=...

TWILIO_SID=...
TWILIO_TOKEN=...
TWILIO_FROM=...
```

4. **Migrations**
```bash
php artisan migrate
php artisan db:seed
```

5. **Compilation frontend**
```bash
npm run dev
# ou pour production
npm run build
```

## 🔐 Authentification

Le système utilise Laravel Sanctum pour l'API et Inertia.js pour le back-office.

### Admin
- Route: `/admin/login`
- Middleware: `auth:sanctum` ou session

### API Publique
- Endpoints publics pour création de réservation
- Endpoints protégés avec Sanctum pour suivi

## 📚 Documentation API

La documentation OpenAPI est disponible via Swagger après déploiement :
- URL: `/api/documentation`

### Endpoints Principaux

#### Public
- `POST /api/v1/reservations` - Créer une réservation
- `GET /api/v1/reservations/{uuid}` - Suivre une réservation
- `POST /api/v1/reservations/{uuid}/add-options` - Ajouter des options

#### Admin
- `GET /api/v1/admin/reservations` - Liste des réservations
- `PUT /api/v1/admin/reservations/{id}/assign` - Assigner date/ressource
- `POST /api/v1/admin/reservations/{id}/capture` - Capturer paiement
- `POST /api/v1/admin/reservations/{id}/refund` - Rembourser

## 🎯 Flux Métier

### 1. Réservation Initiale
1. Client remplit formulaire (type vol, participants, options optionnelles)
2. Application coupon/bon cadeau si applicable
3. Création PaymentIntent Stripe avec `capture_method: manual`
4. Montant = acompte (configurable) ou empreinte totale
5. Statut réservation = `pending` (en attente d'assignation)
6. Email confirmation envoyé

### 2. Assignation Date
1. Admin assigne date, moniteur, site dans le calendrier
2. Email + SMS notification au client
3. Rappel automatique programmé 24h avant

### 3. Upsell Options
- Client peut ajouter options avant assignation via lien email
- Admin peut ajouter options dans back-office
- Nouveau PaymentIntent créé si nécessaire pour complément

### 4. Post-Vol
1. Admin marque réservation comme `completed`
2. Tentative d'upsell photo/vidéo si pas déjà pris
3. Capture automatique du paiement final
4. Email remerciement + lien avis + facture

## 🧪 Tests

```bash
php artisan test
```

## 📦 Déploiement

### Production Checklist
- [ ] Variables d'environnement configurées
- [ ] Base de données migrée
- [ ] Webhook Stripe configuré
- [ ] Queue workers actifs
- [ ] Scheduler cron configuré
- [ ] HTTPS activé
- [ ] Cache optimisé

### Commandes Scheduler (crontab)
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## 📞 Support

Pour toute question technique, consulter la documentation complète dans `/docs`.
