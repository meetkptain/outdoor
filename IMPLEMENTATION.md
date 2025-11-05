# 🚀 Guide d'Implémentation

## Phase 1 : Installation Initiale

### 1. Prérequis

```bash
# Vérifier versions
php -v  # >= 8.2
composer --version
node -v  # >= 18
psql --version  # >= 14
redis-cli --version
```

### 2. Installation Laravel

```bash
composer create-project laravel/laravel parapente
cd parapente
```

### 3. Installation Dépendances

```bash
composer require stripe/stripe-php
composer require twilio/sdk
composer require laravel/sanctum
composer require laravel/tinker
```

### 4. Configuration Environnement

```bash
cp .env.example .env
php artisan key:generate
```

Éditer `.env` :
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=parapente

STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

MAIL_MAILER=mailgun
MAILGUN_DOMAIN=...
MAILGUN_SECRET=...
```

---

## Phase 2 : Base de Données

### 1. Migrations

```bash
# Copier les migrations créées dans database/migrations/
php artisan migrate
```

### 2. Seeders (Optionnel)

Créer des données de test :
```bash
php artisan make:seeder OptionSeeder
php artisan make:seeder SiteSeeder
php artisan make:seeder ResourceSeeder
php artisan db:seed
```

---

## Phase 3 : Configuration Services

### 1. Stripe

1. Créer compte Stripe
2. Récupérer clés API (test)
3. Configurer webhook endpoint :
   - URL : `https://votre-domaine.com/api/webhooks/stripe`
   - Événements : `payment_intent.*`, `charge.refunded`
   - Récupérer le secret webhook

### 2. Mailgun

1. Créer compte Mailgun
2. Vérifier domaine
3. Récupérer API key
4. Configurer dans `.env`

### 3. Twilio (Optionnel)

1. Créer compte Twilio
2. Obtenir numéro téléphone
3. Récupérer SID et Token
4. Configurer dans `.env`

---

## Phase 4 : Classes Mail

Créer les classes Mail manquantes :

```bash
php artisan make:mail ReservationConfirmationMail
php artisan make:mail AssignmentNotificationMail
php artisan make:mail ReminderMail
php artisan make:mail UpsellAfterFlightMail
php artisan make:mail ThankYouMail
php artisan make:mail OptionsAddedMail
```

Implémenter dans `app/Mail/` selon les templates nécessaires.

---

## Phase 5 : Middleware Webhook

Créer middleware pour vérifier signature Stripe :

```bash
php artisan make:middleware VerifyStripeWebhook
```

Implémenter dans `app/Http/Middleware/VerifyStripeWebhook.php`

Enregistrer dans `app/Http/Kernel.php` :
```php
'verify.stripe.webhook' => \App\Http\Middleware\VerifyStripeWebhook::class,
```

---

## Phase 6 : Tests

### Tests Unitaires

```bash
php artisan make:test ReservationServiceTest
php artisan make:test PaymentServiceTest
php artisan test
```

### Tests d'Intégration

```bash
php artisan make:test ReservationFlowTest
```

---

## Phase 7 : Frontend (Optionnel)

### Back-Office Vue.js

```bash
npm install
npm install @inertiajs/inertia @inertiajs/inertia-vue3
npm install vue@next @vitejs/plugin-vue
```

Créer les pages Inertia :
- `resources/js/Pages/Admin/Reservations/Index.vue`
- `resources/js/Pages/Admin/Reservations/Show.vue`
- `resources/js/Pages/Admin/Calendar.vue`

---

## Phase 8 : Déploiement

### 1. Préparation Production

```bash
# Optimisations
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### 2. Queue Workers

Configurer supervisor/systemd pour :
```bash
php artisan queue:work redis --sleep=3 --tries=3
```

### 3. Scheduler

Ajouter au crontab :
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### 4. Webhook Stripe

Configurer l'endpoint en production dans le dashboard Stripe.

---

## Checklist Finale

- [ ] Toutes migrations exécutées
- [ ] Services externes configurés (Stripe, Mailgun, Twilio)
- [ ] Queue workers actifs
- [ ] Scheduler configuré
- [ ] Webhooks Stripe testés
- [ ] Tests passent
- [ ] HTTPS activé
- [ ] Monitoring configuré
- [ ] Backup DB automatique
- [ ] Documentation API accessible

---

## Prochaines Étapes (Phase 2)

1. Back-office complet (calendrier, dashboard)
2. Widgets JS embeddables
3. Reporting avancé
4. Automatisations
5. PWA

---

**Bon développement ! 🪂**
