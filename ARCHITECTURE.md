# 🏗️ Architecture du Système de Réservation Parapente

## 📐 Vue d'Ensemble

Système complet de gestion de réservations pour club de parapente avec paiement en deux temps, gestion de ressources, upsell d'options et back-office complet.

---

## 🗄️ Modèle de Données

### Entités Principales

#### Reservation
- **Rôle** : Entité centrale du système
- **Statuts** : `pending`, `assigned`, `confirmed`, `completed`, `cancelled`, `postponed`, `refunded`
- **Paiement** : Support acompte, empreinte ou les deux
- **Relations** : User, Instructor, Site, Resources, Options, Payments, Flights, Coupon, GiftCard

#### Payment
- **Types** : `deposit`, `authorization`, `capture`, `refund`, `adjustment`
- **Intégration Stripe** : PaymentIntent avec `manual_capture`
- **Workflow** : Authorization → Capture (après vol)

#### Option
- **Types** : `photo`, `video`, `souvenir`, `insurance`, `transport`, `other`
- **Stages d'ajout** : `initial`, `before_flight`, `after_flight`
- **Prix** : Fixe ou par participant

#### Resource
- **Types** : `tandem_glider`, `site`, `vehicle`, `equipment`
- **Gestion** : Disponibilités, maintenance, caractéristiques

---

## 🔄 Flux Métier Principaux

### 1. Réservation Initiale

```
Client → Formulaire → Validation → Création Reservation
  ↓
Calcul Montants (base + options - réductions)
  ↓
Création PaymentIntent Stripe (manual_capture)
  ↓
Authorization/Deposit → Statut: pending
  ↓
Email Confirmation
```

**Points clés** :
- Pas de date assignée immédiatement
- Paiement non capturé (empreinte ou acompte seulement)
- Client peut ajouter options ultérieurement

### 2. Assignation Date

```
Admin → Calendrier → Sélection Date/Ressources
  ↓
Vérification Disponibilités
  ↓
Mise à jour Reservation (status: assigned)
  ↓
Email + SMS Notification Client
  ↓
Programmation Rappel 24h avant
```

### 3. Ajout d'Options

```
Client/Admin → Sélection Options
  ↓
Calcul Montant Supplémentaire
  ↓
Nouveau PaymentIntent (si paiement immédiat)
  OU
Ajout au montant total (capture post-vol)
  ↓
Mise à jour Reservation
```

### 4. Post-Vol

```
Admin → Marquer Réservation "completed"
  ↓
Tentative Upsell Photo/Video
  ↓
Capture Paiement Final (PaymentIntent.capture)
  ↓
Email Remerciement + Facture
```

---

## 🔌 API Endpoints

### Public

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/v1/reservations` | Créer réservation |
| GET | `/api/v1/reservations/{uuid}` | Suivre réservation |
| POST | `/api/v1/reservations/{uuid}/add-options` | Ajouter options |

### Admin (Authentifié)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/v1/admin/reservations` | Liste réservations |
| GET | `/api/v1/admin/reservations/{id}` | Détails réservation |
| PUT | `/api/v1/admin/reservations/{id}/assign` | Assigner date/ressources |
| POST | `/api/v1/admin/reservations/{id}/add-options` | Ajouter options |
| POST | `/api/v1/admin/reservations/{id}/capture` | Capturer paiement |
| POST | `/api/v1/admin/reservations/{id}/refund` | Rembourser |
| POST | `/api/v1/admin/reservations/{id}/complete` | Marquer complété |

### Webhooks

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/webhooks/stripe` | Événements Stripe |

**Événements gérés** :
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `payment_intent.requires_capture`
- `charge.refunded`

---

## 💳 Intégration Stripe

### PaymentIntent avec Manual Capture

```php
$intent = $stripe->paymentIntents->create([
    'amount' => $amount * 100,
    'currency' => 'eur',
    'payment_method' => $paymentMethodId,
    'capture_method' => 'manual', // ⚠️ Capture manuelle
    'confirmation_method' => 'manual',
    'confirm' => true,
]);
```

### Workflow

1. **Authorization** : Client autorise le paiement
   - Statut Stripe : `requires_capture`
   - Statut local : `authorized`

2. **Capture** : Après le vol, admin capture
   ```php
   $stripe->paymentIntents->capture($intentId, [
       'amount_to_capture' => $amount * 100 // Optionnel: capture partielle
   ]);
   ```

3. **Refund** : Remboursement si nécessaire
   ```php
   $stripe->refunds->create([
       'payment_intent' => $intentId,
       'amount' => $amount * 100
   ]);
   ```

### Réautorisation si > 7 jours

Stripe expire les autorisations après 7 jours. Le système doit :
1. Détecter les autorisations > 7 jours
2. Annuler l'ancienne autorisation
3. Demander nouvelle autorisation (nécessite SetupIntent ou re-saisie carte)

---

## 📧 Système de Notifications

### Types

- **Email** : Via Mailgun/Laravel Mail
- **SMS** : Via Twilio
- **Queue** : Toutes notifications en queue Redis

### Templates

1. **ReservationConfirmation** : Après création réservation
2. **AssignmentNotification** : Quand date assignée
3. **ReminderMail** : 24h avant le vol
4. **UpsellAfterFlight** : Proposition photo/vidéo
5. **ThankYouMail** : Après vol + facture

### Traçabilité

Toutes notifications sauvegardées dans table `notifications` avec :
- Statut (pending, sent, failed)
- Timestamps
- Métadonnées

---

## 🎯 Règles Métier Importantes

### Paiement

1. **Jamais de capture avant le vol**
   - Validation automatique dans `PaymentService::capturePayment()`

2. **Autorisation expire après 7 jours**
   - Check automatique avant capture
   - Nécessite réautorisation

3. **Options ajoutées après autorisation**
   - Nouveau PaymentIntent créé pour complément
   - OU ajouté au montant total (capture post-vol)

### Réservations

1. **Statut progression** :
   ```
   pending → assigned → confirmed → completed
   ```

2. **Annulation** :
   - Statut → `cancelled`
   - Remboursement selon politique
   - Raison obligatoire

3. **Report météo** :
   - Statut → `postponed`
   - Nouvelle date à assigner

### Options

1. **Stages d'ajout** :
   - `initial` : Lors création réservation
   - `before_flight` : Avant assignation/vol
   - `after_flight` : Post-vol (upsell)

2. **Prix** :
   - Fixe ou par participant
   - Sauvegardé au moment de l'ajout (historique)

---

## 🔒 Sécurité

### Authentification

- **API Admin** : Laravel Sanctum (tokens)
- **API Publique** : Pas d'auth requise (création réservation)

### Validation

- Validation stricte des données entrantes
- Sanitization des inputs
- Rate limiting sur endpoints sensibles

### Webhooks Stripe

- Vérification signature avec `Stripe-Signature` header
- Middleware dédié : `verify.stripe.webhook`

### Données Sensibles

- Cartes : Jamais stockées (Stripe PaymentMethod)
- Données personnelles : RGPD compliant
- Logs : Pas de données sensibles

---

## 📊 Performance & Scalabilité

### Optimisations

1. **Eager Loading** : Relations chargées à la demande
2. **Index DB** : Sur colonnes fréquemment recherchées
3. **Cache** : Options, sites, ressources en cache
4. **Queue** : Notifications et jobs lourds

### Scaling

- **Horizontal** : Multi-instances avec load balancer
- **Database** : Read replicas pour requêtes SELECT
- **Queue** : Workers multiples (Redis)

---

## 🧪 Tests

### Unitaires

- Modèles : Relations, scopes, méthodes
- Services : Logique métier
- Controllers : Validation, réponses

### Intégration

- Flux complet réservation
- Intégration Stripe (mock)
- Webhooks Stripe

### E2E

- Parcours client complet
- Back-office admin

---

## 📦 Déploiement

### Prérequis

- PHP 8.2+
- PostgreSQL 14+
- Redis
- Composer
- Node.js 18+ (frontend)

### Variables d'Environnement

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=parapente

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

---

## 🔄 Évolutions Futures (Phase 2)

1. **Widgets JS Embeddables**
   - Formulaire réservation intégrable
   - Suivi réservation embeddable

2. **PWA Back-Office**
   - Application mobile admin
   - Notifications push

3. **Reporting Avancé**
   - Analytics temps réel
   - Export Excel/PDF
   - Graphiques de performance

4. **Automatisations**
   - Assignation automatique selon disponibilités
   - Rappels SMS personnalisés
   - Upsell automatisé selon profil client

---

## 📚 Documentation API

La documentation OpenAPI/Swagger sera disponible via :
- Endpoint : `/api/documentation`
- Génération : Laravel API Documentation Generator

---

## 🆘 Support & Maintenance

### Logs

- Tous événements critiques loggés
- Errors : `storage/logs/laravel.log`
- Stripe webhooks : Table `notifications`

### Monitoring

- Health check endpoint : `/api/health`
- Queue monitoring : Horizon (si installé)
- DB monitoring : Slow queries log

---

## 📝 Notes d'Implémentation

### Dépendances Requises

```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "stripe/stripe-php": "^10.0",
        "twilio/sdk": "^7.0",
        "laravel/sanctum": "^4.0"
    }
}
```

### Commandes Utiles

```bash
# Migrations
php artisan migrate

# Queue Workers
php artisan queue:work redis

# Scheduler (à ajouter au crontab)
* * * * * php artisan schedule:run

# Tests
php artisan test
```

---

**Document mis à jour** : Version 1.0 - Architecture complète
