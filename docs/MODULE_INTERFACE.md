# 📦 Documentation Interface Module

**Date de création:** 2025-11-06  
**Version:** 1.0.0  
**Objectif:** Standardiser les modules d'activité et faciliter l'extension du système

---

## 🎯 Vue d'Ensemble

Le système de modules permet d'ajouter facilement de nouvelles activités (paragliding, surfing, diving, etc.) au système SaaS multi-niche. Chaque module encapsule la logique spécifique à son activité tout en respectant une interface commune.

---

## 📋 Structure des Modules

### Fichiers Requis

Chaque module doit avoir la structure suivante :

```
app/Modules/
└── NomModule/
    ├── config.php              # Configuration du module (requis)
    ├── NomModuleModule.php     # Classe du module (optionnel, utilise BaseModule si absent)
    ├── Models/                 # Modèles spécifiques (optionnel)
    ├── Controllers/            # Contrôleurs spécifiques (optionnel)
    └── Services/              # Services spécifiques (optionnel)
```

### Exemple : Module Paragliding

```
app/Modules/Paragliding/
├── config.php
├── ParaglidingModule.php
├── Models/
│   ├── Biplaceur.php
│   └── Flight.php
└── Services/
```

---

## 🔌 Interface ModuleInterface

Tous les modules doivent implémenter `ModuleInterface` ou étendre `BaseModule`.

### Méthodes Obligatoires

#### Informations de Base

```php
public function getName(): string;
public function getActivityType(): string;
public function getVersion(): string;
public function getConfig(): array;
```

#### Configuration

```php
public function getConstraints(): array;
public function getFeatures(): array;
public function getWorkflow(): array;
public function getModels(): array;
```

#### Helpers

```php
public function hasFeature(string $feature): bool;
public function getConstraint(string $key, $default = null);
public function getFeature(string $key, $default = null);
public function get(string $key, $default = null);
```

#### Hooks (Optionnels)

```php
public function beforeReservationCreate(array $data): array;
public function afterReservationCreate(Reservation $reservation): void;
public function beforeSessionSchedule(array $data): array;
public function afterSessionComplete(ActivitySession $session): void;
```

#### Routes et Événements (Optionnels)

```php
public function registerRoutes(): void;
public function registerEvents(): void;
```

---

## 🏗️ BaseModule

`BaseModule` est une implémentation complète de `ModuleInterface` avec des méthodes par défaut. Vous pouvez :

1. **Utiliser directement** : Pour des modules simples sans logique spécifique
2. **Étendre** : Pour des modules avec logique personnalisée

### Exemple d'Utilisation Directe

```php
// Dans ModuleServiceProvider, BaseModule sera utilisé automatiquement
// si aucune classe spécifique n'est définie
```

### Exemple d'Extension

```php
namespace App\Modules\Paragliding;

use App\Modules\BaseModule;
use App\Models\Reservation;

class ParaglidingModule extends BaseModule
{
    public function beforeReservationCreate(array $data): array
    {
        // Validation spécifique au parapente
        if (isset($data['customer_weight'])) {
            $min = $this->getConstraint('weight.min', 40);
            $max = $this->getConstraint('weight.max', 120);
            
            if ($data['customer_weight'] < $min || $data['customer_weight'] > $max) {
                throw new \Exception("Poids invalide pour le parapente");
            }
        }
        
        return $data;
    }
    
    public function afterReservationCreate(Reservation $reservation): void
    {
        // Actions post-création spécifiques au parapente
        // Ex: Préparer la logique de navettes
    }
}
```

---

## 🎣 Système de Hooks

Les hooks permettent aux modules de s'intégrer dans le workflow de l'application.

### Hooks Disponibles

#### Réservations

- `BEFORE_RESERVATION_CREATE` : Avant création
- `AFTER_RESERVATION_CREATE` : Après création
- `BEFORE_RESERVATION_UPDATE` : Avant mise à jour
- `AFTER_RESERVATION_UPDATE` : Après mise à jour
- `BEFORE_RESERVATION_CANCEL` : Avant annulation
- `AFTER_RESERVATION_CANCEL` : Après annulation

#### Sessions

- `BEFORE_SESSION_SCHEDULE` : Avant planification
- `AFTER_SESSION_SCHEDULE` : Après planification
- `BEFORE_SESSION_COMPLETE` : Avant complétion
- `AFTER_SESSION_COMPLETE` : Après complétion
- `BEFORE_SESSION_CANCEL` : Avant annulation
- `AFTER_SESSION_CANCEL` : Après annulation

#### Paiements

- `BEFORE_PAYMENT_CAPTURE` : Avant capture
- `AFTER_PAYMENT_CAPTURE` : Après capture
- `BEFORE_PAYMENT_REFUND` : Avant remboursement
- `AFTER_PAYMENT_REFUND` : Après remboursement

#### Instructeurs

- `BEFORE_INSTRUCTOR_ASSIGN` : Avant assignation
- `AFTER_INSTRUCTOR_ASSIGN` : Après assignation

### Utilisation des Hooks

#### Dans un Module

```php
class MyModule extends BaseModule
{
    public function beforeReservationCreate(array $data): array
    {
        // Modifier les données avant création
        $data['custom_field'] = 'value';
        return $data;
    }
    
    public function afterReservationCreate(Reservation $reservation): void
    {
        // Actions post-création
        // Ex: Envoyer notification, créer session, etc.
    }
}
```

#### Dans ModuleRegistry

```php
$registry = app(ModuleRegistry::class);

// Enregistrer un hook personnalisé
$registry->registerHook(
    ModuleHook::BEFORE_RESERVATION_CREATE,
    $module,
    function ($data) {
        // Logique personnalisée
        return $data;
    }
);

// Déclencher un hook
$result = $registry->triggerHook(
    ModuleHook::BEFORE_RESERVATION_CREATE,
    'paragliding',
    $data
);
```

---

## 📝 Configuration du Module (config.php)

### Structure Minimale

```php
<?php

return [
    'name' => 'Nom du Module',
    'version' => '1.0.0',
    'activity_type' => 'identifiant_unique',
    'constraints' => [],
    'features' => [],
    'workflow' => [],
];
```

### Exemple Complet

```php
<?php

return [
    'name' => 'Paragliding',
    'version' => '1.0.0',
    'activity_type' => 'paragliding',
    
    // Modèles spécifiques (optionnel)
    'models' => [
        'reservation' => \App\Modules\Paragliding\Models\ParaglidingReservation::class,
        'session' => \App\Modules\Paragliding\Models\Flight::class,
        'instructor' => \App\Modules\Paragliding\Models\Biplaceur::class,
    ],
    
    // Contraintes de validation
    'constraints' => [
        'weight' => ['min' => 40, 'max' => 120],
        'height' => ['min' => 140, 'max' => 250],
        'age' => ['min' => 12],
    ],
    
    // Fonctionnalités activées
    'features' => [
        'shuttles' => true,
        'weather_dependent' => true,
        'rotation_duration' => 90, // minutes
        'max_shuttle_capacity' => 9,
        'instant_booking' => false,
    ],
    
    // Workflow de réservation
    'workflow' => [
        'stages' => ['pending', 'authorized', 'scheduled', 'completed'],
        'auto_schedule' => false,
    ],
];
```

---

## 🚀 Créer un Nouveau Module

### Étape 1 : Créer la Structure

```bash
mkdir -p app/Modules/NouvelleActivite/{Models,Controllers,Services}
```

### Étape 2 : Créer config.php

```php
<?php
// app/Modules/NouvelleActivite/config.php

return [
    'name' => 'Nouvelle Activité',
    'version' => '1.0.0',
    'activity_type' => 'nouvelle_activite',
    'constraints' => [
        'age' => ['min' => 10],
    ],
    'features' => [
        'equipment_rental' => true,
    ],
    'workflow' => [
        'stages' => ['pending', 'confirmed', 'completed'],
        'auto_schedule' => true,
    ],
];
```

### Étape 3 : Créer la Classe Module (Optionnel)

```php
<?php
// app/Modules/NouvelleActivite/NouvelleActiviteModule.php

namespace App\Modules\NouvelleActivite;

use App\Modules\BaseModule;
use App\Models\Reservation;

class NouvelleActiviteModule extends BaseModule
{
    public function beforeReservationCreate(array $data): array
    {
        // Logique spécifique
        return $data;
    }
}
```

### Étape 4 : Enregistrer dans ModuleServiceProvider

```php
// app/Providers/ModuleServiceProvider.php

protected array $moduleClasses = [
    'Paragliding' => \App\Modules\Paragliding\ParaglidingModule::class,
    'Surfing' => \App\Modules\Surfing\SurfingModule::class,
    'NouvelleActivite' => \App\Modules\NouvelleActivite\NouvelleActiviteModule::class,
];
```

### Étape 5 : Le Module est Découvert Automatiquement !

Le `ModuleServiceProvider` charge automatiquement tous les modules depuis le dossier `app/Modules/`.

---

## 🔄 Intégration dans les Services

Les hooks sont automatiquement appelés dans les services :

### ReservationService

```php
// Avant création
$module = $this->moduleRegistry->get($activity->activity_type);
if ($module) {
    $data = $module->beforeReservationCreate($data);
}

// Après création
if ($module) {
    $module->afterReservationCreate($reservation);
}
```

### InstructorService

```php
// Après complétion de session
$module = $this->moduleRegistry->get($activity->activity_type);
if ($module) {
    $module->afterSessionComplete($session);
}
```

---

## ✅ Bonnes Pratiques

### 1. Validation dans beforeReservationCreate

```php
public function beforeReservationCreate(array $data): array
{
    // Valider les contraintes
    if (isset($data['customer_weight'])) {
        $min = $this->getConstraint('weight.min');
        $max = $this->getConstraint('weight.max');
        
        if ($data['customer_weight'] < $min || $data['customer_weight'] > $max) {
            throw new \Exception("Poids invalide");
        }
    }
    
    return $data;
}
```

### 2. Actions Post-Création

```php
public function afterReservationCreate(Reservation $reservation): void
{
    // Ne pas faire de modifications lourdes ici
    // Utiliser des jobs/queues pour les actions asynchrones
    // Ex: Envoyer emails, créer sessions, etc.
}
```

### 3. Utilisation des Features

```php
if ($module->hasFeature('shuttles')) {
    // Logique spécifique aux navettes
}

$duration = $module->getFeature('rotation_duration', 30);
```

### 4. Workflow Personnalisé

```php
$workflow = $module->getWorkflow();
$stages = $workflow['stages'] ?? ['pending', 'completed'];

if ($workflow['auto_schedule'] ?? false) {
    // Planification automatique
}
```

---

## 🧪 Tests

### Exemple de Test

```php
public function test_module_hooks(): void
{
    $registry = app(ModuleRegistry::class);
    $module = $registry->get('paragliding');
    
    $data = ['customer_weight' => 75];
    $modified = $module->beforeReservationCreate($data);
    
    $this->assertIsArray($modified);
}
```

---

## 📚 Références

- **Interface** : `app/Modules/ModuleInterface.php`
- **Classe de Base** : `app/Modules/BaseModule.php`
- **Hooks** : `app/Modules/ModuleHook.php`
- **Registry** : `app/Modules/ModuleRegistry.php`
- **Service Provider** : `app/Providers/ModuleServiceProvider.php`

---

## 🔍 Exemples de Modules

### Module Paragliding

- **Fichier** : `app/Modules/Paragliding/ParaglidingModule.php`
- **Spécificités** : Validation poids/taille, gestion navettes, dépendance météo

### Module Surfing

- **Fichier** : `app/Modules/Surfing/SurfingModule.php`
- **Spécificités** : Validation âge, gestion équipement, dépendance marée

---

**Date de création:** 2025-11-06  
**Dernière mise à jour:** 2025-11-06  
**Créé par:** Auto (IA Assistant)

