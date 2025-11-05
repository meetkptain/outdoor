# 🗺️ Roadmap de Développement - Système de Gestion Parapente

## 📅 Vue d'Ensemble (8 Semaines)

Cette roadmap détaille le développement complet du système de gestion parapente, de la phase de fondations jusqu'à la mise en production.

---

## 🏗️ PHASE 1 - Fondations (Semaine 1-2)

### Objectifs
- Structure de base Laravel
- Base de données complète
- Authentification multi-rôles
- Services de base

### Tâches

#### Semaine 1
- [x] **Migration Users avec rôles**
  - Table users avec champ role (admin, biplaceur, client)
  - Migration complète

- [x] **Migrations Clients & Biplaceurs**
  - Table clients (extension users)
  - Table biplaceurs (extension users)
  - Relations avec users

- [x] **Migration Réservations mise à jour**
  - Ajout client_id, biplaceur_id
  - Statuts complets (authorized, scheduled, rescheduled)
  - Index optimisés

- [x] **Migrations Signatures & Reports**
  - Table signatures (décharges)
  - Table reports (reports météo)

- [x] **Modèles Eloquent**
  - User, Client, Biplaceur
  - Signature, Report
  - Relations complètes

- [ ] **Tests migrations**
  - Vérifier toutes les migrations
  - Relations foreign keys

#### Semaine 2
- [x] **Authentification Laravel Sanctum**
  - Installation et configuration
  - Endpoints register/login/logout

- [x] **Middleware Rôles**
  - RoleMiddleware créé
  - Enregistrement dans bootstrap/app.php

- [x] **Services de Base**
  - ReservationService (existant, à compléter)
  - PaymentService (existant, à compléter)
  - BiplaceurService
  - ClientService
  - DashboardService
  - StripeTerminalService

- [ ] **Tests Unitaires Services**
  - Tests de base pour chaque service
  - Mock Stripe

---

## 🔌 PHASE 2 - API Core (Semaine 3-4)

### Objectifs
- Endpoints authentification complets
- Endpoints réservations (public + admin)
- Endpoints paiements Stripe
- Webhooks Stripe

### Tâches

#### Semaine 3
- [ ] **Contrôleur AuthController**
  - POST /api/v1/auth/register
  - POST /api/v1/auth/login
  - POST /api/v1/auth/logout
  - GET /api/v1/auth/me

- [ ] **Contrôleur ReservationController (Public)**
  - POST /api/v1/reservations (créer)
  - GET /api/v1/reservations/{uuid} (suivre)
  - POST /api/v1/reservations/{uuid}/add-options

- [ ] **Contrôleur ReservationController (Client)**
  - GET /api/v1/my/reservations
  - GET /api/v1/my/reservations/{id}
  - POST /api/v1/my/reservations/{id}/add-options

- [ ] **Contrôleur ReservationAdminController**
  - GET /api/v1/admin/reservations (liste avec filtres)
  - GET /api/v1/admin/reservations/{id}
  - POST /api/v1/admin/reservations/{id}/schedule
  - PUT /api/v1/admin/reservations/{id}/assign

#### Semaine 4
- [ ] **Contrôleur PaymentController**
  - POST /api/v1/payments/intent
  - POST /api/v1/payments/capture
  - POST /api/v1/payments/refund

- [ ] **StripeTerminalService - Intégration**
  - POST /api/v1/payments/terminal/connection-token
  - POST /api/v1/payments/terminal/payment-intent
  - POST /api/v1/payments/qr/create

- [ ] **StripeWebhookController**
  - Gestion payment_intent.succeeded
  - Gestion payment_intent.payment_failed
  - Gestion payment_intent.canceled
  - Gestion charge.refunded
  - Gestion setup_intent.succeeded

- [ ] **Tests Intégration Stripe**
  - Tests webhooks (mock)
  - Tests PaymentIntent
  - Tests capture différée

---

## 👥 PHASE 3 - Gestion Club (Semaine 5-6)

### Objectifs
- Endpoints biplaceurs complets
- Endpoints clients
- Dashboard admin
- Gestion options, coupons, bons cadeaux

### Tâches

#### Semaine 5
- [ ] **Contrôleur BiplaceurController**
  - GET /api/v1/biplaceurs (liste publique)
  - GET /api/v1/biplaceurs/me/flights (biplaceur)
  - GET /api/v1/biplaceurs/me/flights/today
  - GET /api/v1/biplaceurs/me/calendar
  - PUT /api/v1/biplaceurs/me/availability
  - POST /api/v1/biplaceurs/me/flights/{id}/mark-done
  - POST /api/v1/biplaceurs/me/flights/{id}/reschedule

- [ ] **Contrôleur ClientController**
  - GET /api/v1/clients
  - GET /api/v1/clients/{id}
  - POST /api/v1/clients
  - PUT /api/v1/clients/{id}
  - GET /api/v1/clients/{id}/history

- [ ] **Contrôleur DashboardController**
  - GET /api/v1/admin/dashboard/summary
  - GET /api/v1/admin/dashboard/revenue
  - GET /api/v1/admin/dashboard/flights
  - GET /api/v1/admin/dashboard/top-biplaceurs

#### Semaine 6
- [ ] **Contrôleur OptionController**
  - GET /api/v1/options (public)
  - POST /api/v1/admin/options
  - PUT /api/v1/admin/options/{id}
  - DELETE /api/v1/admin/options/{id}

- [ ] **Contrôleur CouponController**
  - GET /api/v1/admin/coupons
  - POST /api/v1/admin/coupons
  - PUT /api/v1/admin/coupons/{id}
  - DELETE /api/v1/admin/coupons/{id}

- [ ] **Contrôleur GiftCardController**
  - POST /api/v1/giftcards/validate
  - GET /api/v1/admin/giftcards
  - POST /api/v1/admin/giftcards
  - PUT /api/v1/admin/giftcards/{id}

- [ ] **Contrôleur SignatureController**
  - POST /api/v1/signatures/{reservation_id}

- [ ] **Tests Feature**
  - Tests parcours biplaceur complet
  - Tests parcours admin
  - Tests dashboard

---

## 💳 PHASE 4 - Paiements Avancés (Semaine 7)

### Objectifs
- Stripe Terminal complet
- QR code Checkout
- Capture différée robuste
- Remboursements

### Tâches

#### Semaine 7
- [ ] **Stripe Terminal - Intégration Complète**
  - Configuration locations Stripe
  - Connection tokens
  - PaymentIntent terminal
  - Traitement paiements terminaux

- [ ] **QR Code Checkout**
  - Génération QR codes
  - Webhooks Checkout sessions
  - Traitement paiements QR

- [ ] **Amélioration PaymentService**
  - Capture différée avec retry
  - Réautorisation automatique (> 7 jours)
  - Gestion erreurs robuste

- [ ] **Remboursements Avancés**
  - Remboursement total
  - Remboursement partiel
  - Avoirs (crédits)

- [ ] **Tests Paiements**
  - Tests Terminal (mock)
  - Tests QR code
  - Tests réautorisation
  - Tests remboursements

---

## 🚀 PHASE 5 - Fonctionnalités Avancées (Semaine 8)

### Objectifs
- Reports météo
- Notifications push (préparation)
- Export PDF/CSV
- Tests complets
- Documentation

### Tâches

#### Semaine 8
- [ ] **Gestion Reports Météo**
  - Endpoint créer report
  - Résolution reports
  - Notifications automatiques

- [ ] **Notifications Push (Préparation)**
  - Structure Firebase
  - Endpoints registration tokens
  - Jobs notifications

- [ ] **Export PDF/CSV**
  - Export réservations
  - Export factures
  - Export rapports

- [ ] **Tests E2E**
  - Parcours client complet
  - Parcours biplaceur complet
  - Parcours admin complet

- [ ] **Documentation**
  - Swagger/OpenAPI
  - Postman Collection
  - README complet

- [ ] **Optimisations**
  - Cache (options, sites, biplaceurs)
  - Eager loading optimisé
  - Index base de données

- [ ] **Préparation Production**
  - Variables d'environnement
  - Checklist déploiement
  - Monitoring

---

## 📊 Métriques de Succès

### Phase 1
- ✅ Toutes les migrations passent
- ✅ Tous les modèles créés avec relations
- ✅ Authentification fonctionnelle

### Phase 2
- ✅ API réservations opérationnelle
- ✅ Paiements Stripe fonctionnels
- ✅ Webhooks reçus et traités

### Phase 3
- ✅ Dashboard admin avec données réelles
- ✅ Biplaceurs peuvent gérer leurs vols
- ✅ Clients peuvent voir leurs réservations

### Phase 4
- ✅ Tap to Pay fonctionnel
- ✅ QR code fonctionnel
- ✅ Capture différée robuste

### Phase 5
- ✅ Tests > 80% coverage
- ✅ Documentation complète
- ✅ Prêt pour production

---

## 🔄 Itérations & Ajustements

### Points d'Attention
- **Stripe** : Tester en mode test avant production
- **Performances** : Surveiller les requêtes N+1
- **Sécurité** : Validation stricte des entrées
- **UX** : Messages d'erreur clairs

### Ajustements Possibles
- Si retard sur Stripe Terminal → Phase 4 peut être décalée
- Si besoin météo urgent → Phase 5 peut être avancée
- Tests peuvent être faits en parallèle avec développement

---

## 📝 Notes Importantes

1. **Backup régulier** : Faire des backups avant chaque migration importante
2. **Tests** : Écrire les tests en même temps que le code
3. **Documentation** : Mettre à jour la doc à chaque étape
4. **Code Review** : Faire des reviews régulières

---

**Dernière mise à jour** : Semaine 1 - Phase 1 en cours
**Prochaine étape** : Compléter les contrôleurs API

