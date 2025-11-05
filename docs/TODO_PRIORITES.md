# ✅ TODO - Priorités d'Action Immédiate

## 🎯 Situation Actuelle

**Score global** : **78%** ✅  
**Statut** : Système très avancé, proche de la production

---

## 🔴 Priorité HAUTE - À FAIRE EN PREMIER (2-3 semaines)

### 1. Scheduler Laravel ⏰ (3-4 jours)

**Objectif** : Automatiser les tâches récurrentes

**À créer** :
- `routes/console.php` (Laravel 11) ou `app/Console/Kernel.php`
- Commandes pour :
  - Rappels automatiques 24h avant vol
  - Nettoyage données anciennes
  - Vérification autorisations expirées
  - Rapports quotidiens

**Fichiers à créer** :
```
app/Console/Commands/
├── SendRemindersCommand.php
├── CleanupOldDataCommand.php
├── CheckExpiredAuthorizationsCommand.php
└── GenerateDailyReportCommand.php
```

**Code exemple** :
```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\SendRemindersCommand;

Schedule::command(SendRemindersCommand::class)->dailyAt('08:00');
Schedule::command('cleanup:old-data')->weekly();
Schedule::command('check:expired-auths')->hourly();
```

**Effort** : 3-4 jours

---

### 2. Tests Essentiels 🧪 (1 semaine)

**Objectif** : Couverture minimale 50-60% pour production

**Tests à créer en priorité** :

1. **Tests Services Critiques** (3 jours)
   - `PaymentServiceTest` (avec mocks Stripe)
   - `VehicleServiceTest` (capacité, poids)
   - `ReservationServiceTest` (compléter)
   - `BiplaceurServiceTest`

2. **Tests Intégration** (2 jours)
   - `StripeWebhookTest` (simuler webhooks)
   - `ReservationFlowTest` (flux complet)
   - `PaymentFlowTest` (paiement complet)

3. **Tests Feature API** (2 jours)
   - `PaymentControllerTest`
   - `BiplaceurControllerTest`
   - `AdminControllerTest`

**Effort** : 1 semaine

---

### 3. Compléter Webhooks Stripe 🔄 (2-3 jours)

**Objectif** : Gérer tous les événements critiques

**À ajouter dans `StripeWebhookController.php`** :

```php
case 'payment_intent.canceled':
    $this->handlePaymentIntentCanceled($event->data->object);
    break;

case 'setup_intent.succeeded':
    $this->handleSetupIntentSucceeded($event->data->object);
    break;
```

**Méthodes à ajouter** :
- `handlePaymentIntentCanceled()` - Nettoyer autorisation
- `handleSetupIntentSucceeded()` - Sauvegarder méthode paiement

**Effort** : 2-3 jours

---

### 4. Configuration Production 🔧 (2-3 jours)

**Checklist** :
- [ ] Variables `.env` production
- [ ] Queue workers (Supervisor/systemd)
- [ ] Scheduler cron configuré
- [ ] Webhook Stripe URL production
- [ ] HTTPS activé
- [ ] Cache Redis configuré
- [ ] Backup DB automatique
- [ ] Logging production (Sentry, etc.)

**Effort** : 2-3 jours

---

## 🟡 Priorité MOYENNE - Important mais pas bloquant (2-3 semaines)

### 5. Documentation API Swagger 📚 (1 semaine)

**À faire** :
1. Installer `darkaonline/l5-swagger`
2. Annoter tous les contrôleurs
3. Générer documentation
4. Exposer sur `/api/documentation`

**Effort** : 1 semaine

---

### 6. Tests Complets 🧪 (1-2 semaines)

**Couverture cible** : 70-80%

**Tests à ajouter** :
- Tous les services
- Tous les contrôleurs
- Tous les modèles
- Tests E2E complets

**Effort** : 1-2 semaines

---

### 7. SMS Notifications 📱 (3-4 jours)

**À faire** :
1. Intégrer Twilio dans `NotificationService`
2. Créer méthodes `sendSms()`
3. Templates SMS
4. Envoyer SMS pour dates assignées et reports

**Effort** : 3-4 jours

---

### 8. Export PDF/CSV 📄 (1 semaine)

**À créer** :
- Export factures PDF (DomPDF ou Snappy)
- Export réservations CSV
- Export statistiques Excel
- Export planning biplaceurs

**Effort** : 1 semaine

---

## 🟢 Priorité BASSE - Améliorations futures

### 9. Push Notifications 🔔
- Intégration service push
- Enregistrement tokens
- Envoi push biplaceurs

### 10. Intégration Météo API 🌤️
- API météo automatique
- Alertes conditions
- Annulation automatique

### 11. Gestion Groupes Avancée 👥
- Remises groupe automatiques
- Répartition famille
- Gestion multivol

### 12. Optimisations Performance ⚡
- Cache queries
- Eager loading
- Index supplémentaires
- Queue pour tâches lourdes

### 13. Frontend 🎨
- Interface admin (Vue.js + Inertia)
- Widget public
- App mobile (Flutter)

---

## 📅 Planning Recommandé

### Semaine 1-2 : Production Ready
- ✅ Scheduler Laravel (3-4 jours)
- ✅ Tests essentiels (1 semaine)
- ✅ Webhooks complémentaires (2-3 jours)
- ✅ Configuration production (2-3 jours)

**Résultat** : Système prêt pour production ✅

### Semaine 3-4 : Améliorations
- ✅ Documentation Swagger (1 semaine)
- ✅ Tests complets (1 semaine)
- ✅ SMS Notifications (3-4 jours)

**Résultat** : Système robuste et bien documenté ✅

### Semaine 5+ : Fonctionnalités Avancées
- Export PDF/CSV
- Push Notifications
- Intégration Météo
- Frontend

---

## 🎯 Objectif Final

**Avec les 4 tâches prioritaires (Scheduler, Tests, Webhooks, Config)** :
- ✅ Système **100% fonctionnel** en production
- ✅ Toutes les fonctionnalités métier **opérationnelles**
- ✅ Qualité code **acceptable** pour production

**Temps estimé** : **2-3 semaines** 🚀

---

**Document créé** : TODO Priorités
**Version** : 1.0.0
**Date** : 2024

