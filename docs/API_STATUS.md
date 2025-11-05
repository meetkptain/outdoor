# 📊 État de l'API Backend - Analyse Complète

**Date** : 2025-11-05  
**Version API** : v1  
**Total Routes** : ~80+ endpoints

---

## ✅ **Ce qui est COMPLET**

### 1. **Authentification** ✅
- `POST /api/v1/auth/register` - Inscription
- `POST /api/v1/auth/login` - Connexion
- `POST /api/v1/auth/logout` - Déconnexion
- `GET /api/v1/auth/me` - Profil utilisateur

### 2. **Réservations** ✅
**Public** :
- `POST /api/v1/reservations` - Créer réservation
- `GET /api/v1/reservations/{uuid}` - Suivre réservation
- `POST /api/v1/reservations/{uuid}/add-options` - Ajouter options
- `POST /api/v1/reservations/{uuid}/apply-coupon` - Appliquer coupon
- `POST /api/v1/reservations/{uuid}/reschedule` - Replanifier
- `POST /api/v1/reservations/{uuid}/cancel` - Annuler

**Client authentifié** :
- `GET /api/v1/my/reservations` - Mes réservations
- `GET /api/v1/my/reservations/{id}` - Détails réservation
- `GET /api/v1/my/reservations/{id}/history` - Historique
- `POST /api/v1/my/reservations/{id}/add-options` - Ajouter options

**Admin** :
- `GET /api/v1/admin/reservations` - Liste réservations
- `GET /api/v1/admin/reservations/{id}` - Détails
- `GET /api/v1/admin/reservations/{id}/history` - Historique
- `POST /api/v1/admin/reservations/{id}/schedule` - Planifier
- `PUT /api/v1/admin/reservations/{id}/assign` - Assigner ressources
- `PATCH /api/v1/admin/reservations/{id}/status` - Mettre à jour statut
- `POST /api/v1/admin/reservations/{id}/add-options` - Ajouter options
- `POST /api/v1/admin/reservations/{id}/complete` - Marquer complété
- `POST /api/v1/admin/reservations/{id}/capture` - Capturer paiement
- `POST /api/v1/admin/reservations/{id}/refund` - Rembourser

### 3. **Paiements** ✅
- `POST /api/v1/payments/intent` - Créer PaymentIntent
- `POST /api/v1/payments/capture` - Capturer paiement
- `POST /api/v1/payments/refund` - Rembourser

**Stripe Terminal** :
- `POST /api/v1/payments/terminal/connection-token` - Token connexion
- `POST /api/v1/payments/terminal/payment-intent` - PaymentIntent terminal
- `POST /api/v1/payments/qr/create` - QR code checkout

### 4. **Biplaceurs/Instructeurs** ✅
**Public** :
- `GET /api/v1/biplaceurs` - Liste biplaceurs

**Biplaceur authentifié** :
- `GET /api/v1/biplaceurs/me/flights` - Mes vols
- `GET /api/v1/biplaceurs/me/flights/today` - Vols aujourd'hui
- `GET /api/v1/biplaceurs/me/calendar` - Calendrier
- `PUT /api/v1/biplaceurs/me/availability` - Mettre à jour disponibilité
- `POST /api/v1/biplaceurs/me/flights/{id}/mark-done` - Marquer terminé
- `POST /api/v1/biplaceurs/me/flights/{id}/reschedule` - Replanifier
- `GET /api/v1/biplaceurs/me/flights/{id}/quick-info` - Infos rapides

**Admin** :
- `GET /api/v1/biplaceurs/{id}` - Détails
- `GET /api/v1/biplaceurs/{id}/calendar` - Calendrier
- `POST /api/v1/biplaceurs` - Créer
- `PUT /api/v1/biplaceurs/{id}` - Modifier
- `DELETE /api/v1/biplaceurs/{id}` - Supprimer

### 5. **Clients** ✅
- `GET /api/v1/clients` - Liste clients
- `GET /api/v1/clients/{id}` - Détails client
- `POST /api/v1/clients` - Créer client
- `PUT /api/v1/clients/{id}` - Modifier client
- `GET /api/v1/clients/{id}/history` - Historique client

### 6. **Options** ✅
- `GET /api/v1/options` - Liste options (public)
- `POST /api/v1/options` - Créer option (admin)
- `PUT /api/v1/options/{id}` - Modifier option (admin)
- `DELETE /api/v1/options/{id}` - Supprimer option (admin)

### 7. **Coupons** ✅
- `GET /api/v1/coupons` - Liste coupons (admin)
- `POST /api/v1/coupons` - Créer coupon (admin)
- `PUT /api/v1/coupons/{id}` - Modifier coupon (admin)
- `DELETE /api/v1/coupons/{id}` - Supprimer coupon (admin)

### 8. **Bons Cadeaux** ✅
- `POST /api/v1/giftcards/validate` - Valider bon cadeau (public)
- `GET /api/v1/giftcards` - Liste bons cadeaux (admin)
- `POST /api/v1/giftcards` - Créer bon cadeau (admin)
- `PUT /api/v1/giftcards/{id}` - Modifier bon cadeau (admin)

### 9. **Sites** ✅
- `GET /api/v1/sites` - Liste sites (public)
- `GET /api/v1/sites/{id}` - Détails site (public)
- `POST /api/v1/sites` - Créer site (admin)
- `PUT /api/v1/sites/{id}` - Modifier site (admin)
- `DELETE /api/v1/sites/{id}` - Supprimer site (admin)

### 10. **Ressources** ✅
- `GET /api/v1/admin/resources` - Liste ressources
- `GET /api/v1/admin/resources/vehicles` - Liste véhicules
- `GET /api/v1/admin/resources/tandem-gliders` - Liste parapentes tandem
- `GET /api/v1/admin/resources/available` - Ressources disponibles
- `POST /api/v1/admin/resources` - Créer ressource
- `GET /api/v1/admin/resources/{id}` - Détails ressource
- `PUT /api/v1/admin/resources/{id}` - Modifier ressource
- `DELETE /api/v1/admin/resources/{id}` - Supprimer ressource

### 11. **Dashboard Admin** ✅
- `GET /api/v1/admin/dashboard` - Dashboard principal
- `GET /api/v1/admin/dashboard/summary` - Résumé
- `GET /api/v1/admin/dashboard/stats` - Statistiques
- `GET /api/v1/admin/dashboard/revenue` - Revenus
- `GET /api/v1/admin/dashboard/flights` - Statistiques vols
- `GET /api/v1/admin/dashboard/top-biplaceurs` - Top biplaceurs

### 12. **Rapports** ✅
- `GET /api/v1/admin/reports` - Liste rapports
- `GET /api/v1/admin/reports/daily` - Rapport quotidien
- `GET /api/v1/admin/reports/monthly` - Rapport mensuel

### 13. **Notifications** ✅
- `GET /api/v1/notifications` - Liste notifications
- `GET /api/v1/notifications/unread-count` - Compteur non lues
- `POST /api/v1/notifications/mark-all-read` - Marquer toutes lues
- `GET /api/v1/notifications/{id}` - Détails notification
- `POST /api/v1/notifications/{id}/read` - Marquer comme lue

### 14. **Signatures** ✅
- `POST /api/v1/signatures/{reservation_id}` - Enregistrer signature

### 15. **Stripe Connect** ✅ (Phase 4)
- `POST /api/v1/admin/stripe/connect/account` - Créer compte Connect
- `GET /api/v1/admin/stripe/connect/status` - Statut compte
- `GET /api/v1/admin/stripe/connect/login-link` - Lien login Stripe

### 16. **Subscriptions** ✅ (Phase 4)
- `GET /api/v1/admin/subscriptions` - Liste abonnements
- `POST /api/v1/admin/subscriptions` - Créer abonnement
- `GET /api/v1/admin/subscriptions/current` - Abonnement actuel
- `POST /api/v1/admin/subscriptions/cancel` - Annuler abonnement

### 17. **Webhooks** ✅
- `POST /webhooks/stripe` - Webhook Stripe

---

## ⚠️ **Ce qui MANQUE (selon architecture SaaS multi-niche)**

### 1. **Organizations (Tenants)** ❌
**Manquant** :
- `GET /api/v1/organizations` - Liste organisations (super admin)
- `GET /api/v1/organizations/{id}` - Détails organisation
- `POST /api/v1/organizations` - Créer organisation
- `PUT /api/v1/organizations/{id}` - Modifier organisation
- `DELETE /api/v1/organizations/{id}` - Supprimer organisation
- `GET /api/v1/organizations/{id}/settings` - Paramètres organisation
- `PUT /api/v1/organizations/{id}/settings` - Mettre à jour paramètres
- `GET /api/v1/organizations/{id}/features` - Features activées
- `POST /api/v1/organizations/{id}/features` - Activer feature
- `DELETE /api/v1/organizations/{id}/features/{feature}` - Désactiver feature
- `GET /api/v1/organizations/{id}/branding` - Branding
- `PUT /api/v1/organizations/{id}/branding` - Mettre à jour branding

### 2. **Activities (Multi-niche)** ❌
**Manquant** :
- `GET /api/v1/activities` - Liste activités (par organisation)
- `GET /api/v1/activities/{id}` - Détails activité
- `POST /api/v1/admin/activities` - Créer activité
- `PUT /api/v1/admin/activities/{id}` - Modifier activité
- `DELETE /api/v1/admin/activities/{id}` - Supprimer activité
- `GET /api/v1/activities/by-type/{type}` - Activités par type (paragliding, surfing, etc.)
- `GET /api/v1/activities/{id}/sessions` - Sessions d'une activité
- `GET /api/v1/activities/{id}/availability` - Disponibilités activité

### 3. **Availability Slots** ❌
**Manquant** :
- `GET /api/v1/availability/slots` - Créneaux disponibles
- `GET /api/v1/availability/slots/{activity_id}` - Créneaux par activité
- `POST /api/v1/admin/availability/slots` - Créer créneau
- `PUT /api/v1/admin/availability/slots/{id}` - Modifier créneau
- `DELETE /api/v1/admin/availability/slots/{id}` - Supprimer créneau
- `GET /api/v1/availability/check` - Vérifier disponibilité
- `POST /api/v1/availability/reserve` - Réserver créneau

### 4. **Instructors (Générique)** ⚠️ **INCOHÉRENCE IDENTIFIÉE**
**⚠️ PROBLÈME** : Les modèles ont été généralisés (Phase 2) mais les routes API utilisent encore "biplaceurs" !

**Partiellement implémenté** :
- ❌ Les endpoints existent pour "biplaceurs" mais **PAS** pour le modèle générique "instructors"
- ❌ `GET /api/v1/instructors` - Liste instructeurs (tous types) - **MANQUANT**
- ❌ `GET /api/v1/instructors/by-activity/{activity_type}` - Instructeurs par activité - **MANQUANT**
- ❌ `GET /api/v1/instructors/{id}/sessions` - Sessions instructeur - **MANQUANT**

**Routes actuelles (spécifiques parapente)** :
- ✅ `GET /api/v1/biplaceurs` - Liste biplaceurs (devrait être `/instructors?activity_type=paragliding`)
- ✅ `GET /api/v1/biplaceurs/me/flights` - Mes vols (devrait être `/instructors/me/sessions`)

**Voir** : `docs/API_GENERALIZATION_ISSUE.md` pour le plan de correction

### 5. **Modules spécifiques** ⚠️
**Manquant** :
- `GET /api/v1/modules` - Liste modules disponibles
- `GET /api/v1/modules/{module_type}` - Détails module
- `POST /api/v1/admin/modules/{module_type}/activate` - Activer module
- `DELETE /api/v1/admin/modules/{module_type}/deactivate` - Désactiver module

**Module Paragliding** :
- `GET /api/v1/paragliding/shuttles` - Navettes disponibles
- `POST /api/v1/admin/paragliding/shuttles` - Créer navette
- `GET /api/v1/paragliding/weather` - Conditions météo

**Module Surfing** :
- `GET /api/v1/surfing/equipment` - Équipement disponible
- `GET /api/v1/surfing/tides` - Informations marées
- `POST /api/v1/admin/surfing/equipment` - Gérer équipement

### 6. **Weather Integration** ❌
**Manquant** :
- `GET /api/v1/weather/conditions/{site_id}` - Conditions météo site
- `GET /api/v1/weather/forecast/{site_id}` - Prévisions météo
- `GET /api/v1/weather/alerts/{organization_id}` - Alertes météo

### 7. **Activity Sessions** ⚠️
**Manquant** :
- `GET /api/v1/activity-sessions` - Liste sessions
- `GET /api/v1/activity-sessions/{id}` - Détails session
- `POST /api/v1/admin/activity-sessions` - Créer session
- `PUT /api/v1/admin/activity-sessions/{id}` - Modifier session
- `DELETE /api/v1/admin/activity-sessions/{id}` - Supprimer session

---

## 📊 **Résumé**

### ✅ **Complétion actuelle** : ~75%

**Fonctionnel** :
- ✅ Authentification complète
- ✅ Réservations (workflow complet)
- ✅ Paiements (Stripe + Terminal)
- ✅ Gestion ressources, sites, clients
- ✅ Dashboard & rapports
- ✅ Notifications
- ✅ Stripe Connect & Subscriptions (Phase 4)

**À compléter pour SaaS multi-niche** :
- ❌ **Organizations** : Gestion multi-tenant (CRUD, settings, branding)
- ❌ **Activities** : API pour activités génériques (paragliding, surfing, etc.)
- ❌ **Availability Slots** : Système de créneaux disponibles en temps réel
- ⚠️ **Instructors** : API générique (actuellement seulement "biplaceurs")
- ⚠️ **Modules** : Endpoints pour activer/désactiver modules
- ❌ **Weather** : Intégration météo
- ⚠️ **Activity Sessions** : API pour sessions planifiées

---

## 🎯 **Recommandations**

### **Priorité 1** (Essentiel pour SaaS) :
1. **Organizations API** - Nécessaire pour gérer les tenants
2. **Activities API** - Fondamental pour multi-niche
3. **Availability Slots API** - Réservations en temps réel

### **Priorité 2** (Important pour UX) :
4. **Instructors API générique** - Remplacer biplaceurs par instructeurs
5. **Activity Sessions API** - Gestion des sessions planifiées
6. **Weather API** - Intégration météo

### **Priorité 3** (Nice to have) :
7. **Modules API** - Activation/désactivation modules
8. **Endpoints spécifiques par module** (Paragliding, Surfing, etc.)

---

**Conclusion** : Le backend API est **fonctionnel pour le parapente** mais nécessite des **ajouts pour devenir un SaaS multi-niche complet**. Les endpoints manquants sont principalement liés à la gestion multi-tenant et aux activités génériques.

