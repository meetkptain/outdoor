# 🧭 CHANGELOG

## [1.2.0] – 2025-11-05

### 🚀 **Phase 2 – Généralisation du parapente & système de modules**

**Statut :** ✅ Terminée — 145 tests, 702 assertions, aucune régression.

#### ✨ Nouveautés

* **Modèles génériques créés**

  * `Activity` : modèle de base pour toutes les activités (parapente, surf, plongée…)

  * `ActivitySession` : gère les sessions planifiées

  * `Instructor` : modèle générique remplaçant `Biplaceur`

* **Système de modules**

  * `ModuleRegistry` : registre central des modules

  * `ModuleServiceProvider` : provider auto-chargeant les modules depuis le filesystem

  * **Module "Paragliding"** complet :

    * `Biplaceur` → hérite de `Instructor`

    * `Flight` → hérite de `ActivitySession`

    * Configuration dédiée dans `config.php`

#### 🧩 Migrations & données

* Migration des `biplaceurs` → `instructors` (préservation des données)

* Migration des `flights` → `activity_sessions`

* Ajout des champs `activity_type` et `activity_id` à `Reservation`

* Migration automatique des réservations existantes vers le nouveau système

#### 🧪 Tests

* +19 nouveaux tests pour le système de modules et les modèles génériques

* ✅ 145 tests, 702 assertions — tout passe avec succès

* Rétrocompatibilité confirmée

#### 🗂 Structure créée

```
app/
├── Models/
│   ├── Activity.php
│   ├── ActivitySession.php
│   └── Instructor.php
├── Modules/
│   ├── Module.php
│   ├── ModuleRegistry.php
│   └── Paragliding/
│       ├── config.php
│       └── Models/
│           ├── Biplaceur.php
│           └── Flight.php
└── Providers/
    └── ModuleServiceProvider.php
```

#### 🔮 Prochaines étapes (Phase 3)

* Création d'un **Module "Surf"** pour tester la scalabilité du système

* Introduction d'un **système de permissions multi-module**

* Ajout de tests d'intégration inter-modules

---

## [1.1.0] – 2025-11-05

### 🏗 **Phase 1 – Multi-Tenant Core**

**Statut :** ✅ Terminée — 126 tests, 668 assertions, aucune régression.

#### ✨ Nouveautés

* **Multi-Tenant complet** :

  * Création du modèle `Organization` avec relations et factory

  * Table pivot `organization_roles` pour la gestion des rôles par organisation

  * Trait `GlobalTenantScope` pour scoper automatiquement toutes les requêtes

* **Mise à jour des modèles**

  * Ajout de `organization_id` à tous les modèles : `Reservation`, `Resource`, `Client`, `Site`, `Option`, `Biplaceur`, `Payment`, `Flight`, `Coupon`, `GiftCard`

  * Application du trait `GlobalTenantScope`

  * Ajout de la relation `organization()`

#### ⚙️ Middleware

* Création du `SetTenantContext` :

  * Détection de l'organisation via :

    * Header HTTP `X-Organization-ID`

    * Sous-domaine ou domaine personnalisé

    * Session active

  * Enregistré globalement sur les routes `web` et `api`

#### 🧩 Migration des données existantes

* Création d'une organisation par défaut

* Attribution de toutes les données existantes à cette organisation

#### 🧪 Tests

* 8 tests d'isolation multi-tenant

* ✅ 126 tests, 668 assertions réussies

#### 🔮 Prochaines étapes (Phase 2)

* Généraliser le parapente en module d'activité

* Créer un système de modules

* Refactoriser les modèles spécifiques (`Biplaceur → Instructor`, `Flight → ActivitySession`)

---

## [1.0.0] – 2025-11-04

### 🌱 Initialisation du projet "Outdoor"

* Installation du socle Laravel

* Configuration du dépôt GitHub

* Migrations et modèles initiaux pour `Reservation`, `Client`, `Biplaceur`, etc.

* Tests de base passants

---

### 🧩 Résumé global de progression

| Phase | Objectif principal                  | Statut     | Tests     |
| ----- | ----------------------------------- | ---------- | --------- |
| 1     | Multi-Tenant Core                   | ✅ Terminé  | 126 tests |
| 2     | Généralisation & système de modules | ✅ Terminé  | 145 tests |
| 3     | Extensions d'activités (Surf, Dive) | 🔜 À venir | –         |
